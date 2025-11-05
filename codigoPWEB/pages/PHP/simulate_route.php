<?php
// simulate_route.php - Versão Híbrida (OCM + Google) com Modo Planejamento
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

ob_start();
header('Content-Type: application/json');

$configPath = 'config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Arquivo de configuração não encontrado.']);
    exit;
}
include $configPath;

// --- Verificação de Chaves (Google E OpenChargeMap) ---
if (
    !defined('GOOGLE_MAPS_API_KEY') || 
    !defined('OPEN_CHARGE_MAP_API_KEY') || 
    !defined('TARIFA_MEDIA_KWH')
) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'GOOGLE_MAPS_API_KEY, OPEN_CHARGE_MAP_API_KEY ou TARIFA_MEDIA_KWH não configuradas no config.php.']);
    exit;
}

if (!function_exists('curl_init')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'A extensão cURL do PHP não está habilitada no servidor.']);
    exit;
}

// Classe de Exception customizada
class EVSimulationException extends Exception {
    private $debugInfo;
    public function __construct($message, $debugInfo = []) {
        parent::__construct($message);
        $this->debugInfo = $debugInfo;
    }
    public function getDebugInfo() { return $this->debugInfo; }
}

// --- CONFIGURAÇÃO DO VEÍCULO ---
$vehicle = [
    'battery_capacity' => 75.0,
    'initial_charge'   => 95.0,
    'consumption'      => 17.0,
    'min_charge_stop'  => 8.0,
    'max_charge_stop'  => 100.0,
    'min_charge_dest'  => 10.0,
    'charging_power'   => 50.0, // Potência padrão
    'connector_types'  => [1036, 33] // IDs da OCM: 1036 = CCS (Type 2), 33 = CCS (Type 1)
];

// --- FUNÇÕES DE CÁLCULO ---
define('EARTH_RADIUS_KM', 6371);

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return EARTH_RADIUS_KM * $c;
}

function calculateRange($charge_pct, $vehicle) {
    $energy_kwh = ($charge_pct / 100) * $vehicle['battery_capacity'];
    return ($energy_kwh / $vehicle['consumption']) * 100;
}

/**
 * NOVO: Calcula o "bearing" (direção) de um ponto a outro
 */
function getBearing($lat1, $lng1, $lat2, $lng2) {
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);

    $dLng = $lng2 - $lng1;
    $y = sin($dLng) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);
    
    $brng = atan2($y, $x);
    
    return ($brng + 2 * M_PI) % (2 * M_PI); // Normaliza
}

/**
 * NOVO: Calcula um ponto de destino dado um início, direção e distância
 */
function calculateSimulatedStop($lat, $lng, $bearing_rad, $distance_km) {
    $lat = deg2rad($lat);
    $lng = deg2rad($lng);
    $d = $distance_km / EARTH_RADIUS_KM; // Distância angular

    $newLat = asin(sin($lat) * cos($d) + cos($lat) * sin($d) * cos($bearing_rad));
    $newLng = $lng + atan2(sin($bearing_rad) * sin($d) * cos($lat), cos($d) - sin($lat) * sin($newLat));

    return [
        'lat' => rad2deg($newLat),
        'lng' => rad2deg($newLng)
    ];
}


// --- FUNÇÕES GOOGLE MAPS API (Sem alterações) ---
function google_api_request($url, $params, $timeout = 15) {
    $params['key'] = GOOGLE_MAPS_API_KEY;
    $queryString = http_build_query($params);
    $apiUrl = $url . '?' . $queryString;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EVSimulator/1.0');
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!$response || $httpcode !== 200) {
        error_log("Erro API Google ({$url}): HTTP {$httpcode} - Erro: {$error}");
        return null;
    }
    $data = json_decode($response, true);
    if ($data['status'] !== 'OK') {
        if ($data['status'] == 'ZERO_RESULTS') { return $data; }
        error_log("Erro API Google ({$url}): Status: {$data['status']} - Error: " . ($data['error_message'] ?? 'N/A'));
        return null;
    }
    return $data;
}
function getGoogleDirections($originLat, $originLng, $destLat, $destLng, $waypointsList = []) {
    $url = "https://maps.googleapis.com/maps/api/directions/json";
    $params = ['origin' => "{$originLat},{$originLng}", 'destination' => "{$destLat},{$destLng}", 'units' => 'metric'];
    if (!empty($waypointsList)) {
        $midpoints = array_slice($waypointsList, 1, -1);
        if (!empty($midpoints)) { $params['waypoints'] = 'optimize:true|' . implode('|', $midpoints); }
    }
    $data = google_api_request($url, $params, 30);
    if (empty($data['routes'][0])) {
        error_log("Google Directions falhou: Nenhuma rota encontrada.");
        return null;
    }
    return $data['routes'][0];
}
function getGoogleMatrixDistances($originLat, $originLng, $destinations) {
    if (empty($destinations)) return [];
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json";
    $originString = "{$originLat},{$originLng}";
    $destCoords = [];
    foreach ($destinations as $dest) { $destCoords[] = $dest['lat'] . ',' . $dest['lng']; }
    $destString = implode('|', $destCoords);
    $params = ['origins' => $originString, 'destinations' => $destString, 'units' => 'metric'];
    $data = google_api_request($url, $params, 30);
    if (empty($data['rows'][0]['elements'])) {
        error_log("Google Matrix falhou. Retornando null.");
        return null;
    }
    $distances = [];
    foreach ($data['rows'][0]['elements'] as $element) {
        if ($element['status'] === 'OK') { $distances[] = $element['distance']['value'] / 1000; } 
        else { $distances[] = null; }
    }
    return $distances;
}
// --- FUNÇÃO OPENCHARGEMAP (OCM) (Sem alterações) ---
function findOCMStations($lat, $lng, $radius_km = 100, $vehicle_connectors = []) {
    $url = "https://api.openchargemap.io/v3/poi/";
    $params = [
        'key' => OPEN_CHARGE_MAP_API_KEY, 'output' => 'json', 'latitude' => $lat, 'longitude' => $lng,
        'distance' => $radius_km, 'distanceunit' => 'km', 'maxresults' => 100, 'compact' => true,
        'connectiontypeid' => implode(',', $vehicle_connectors)
    ];
    $queryString = http_build_query($params);
    $apiUrl = $url . '?' . $queryString;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EVSimulator/1.0');
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!$response || $httpcode !== 200) {
        error_log("Erro API OpenChargeMap: HTTP {$httpcode} - Erro: {$error}");
        return [];
    }
    return json_decode($response, true);
}

// --- FUNÇÕES PRINCIPAIS DE SIMULAÇÃO ---

/**
 * findBestChargingStation (Lógica modificada na SOLUÇÃO 1 - aumentando para 15)
 */
function findBestChargingStation($currentLat, $currentLng, $destLat, $destLng, $maxRange_km, $visitedStations, $vehicle) {
    
    // Usar a autonomia MÁXIMA para a busca Haversine da OCM.
    $searchRadius = $maxRange_km;
    
    // 1. Encontrar estações com OpenChargeMap API
    $stations = findOCMStations($currentLat, $currentLng, $searchRadius, $vehicle['connector_types']);
    
    if (empty($stations)) {
        error_log("Nenhuma estação encontrada no raio de {$searchRadius}km via OpenChargeMap");
        return null;
    }

    $candidates = [];

    // 2. Primeira filtragem (Haversine e visitados)
    foreach ($stations as $station) {
        if (!isset($station['AddressInfo']['Latitude']) || !isset($station['AddressInfo']['Longitude'])) continue;
        $stationId = $station['ID'];
        if (in_array($stationId, $visitedStations)) continue; 
        if (empty($station['Connections'])) continue;

        $bestConnection = null;
        foreach ($station['Connections'] as $conn) {
            if (!empty($conn['PowerKW']) && in_array($conn['ConnectionTypeID'], $vehicle['connector_types'])) {
                if ($bestConnection == null || $conn['PowerKW'] > $bestConnection['PowerKW']) {
                    $bestConnection = $conn;
                }
            }
        }
        if ($bestConnection == null) continue;

        $stationLat = $station['AddressInfo']['Latitude'];
        $stationLng = $station['AddressInfo']['Longitude'];
        $distToStation = calculateDistance($currentLat, $currentLng, $stationLat, $stationLng);
        if ($distToStation < 10.0) continue; // Evitar loops
        if ($distToStation > $maxRange_km) continue; // Filtro Haversine
        
        $distToDestination = calculateDistance($stationLat, $stationLng, $destLat, $destLng);
        $currentDistToDest = calculateDistance($currentLat, $currentLng, $destLat, $destLng);
        $score = $currentDistToDest - $distToDestination;
        
        $candidates[] = [
            'score' => $score, 'station_data' => $station, 'connection_info' => $bestConnection,
            'haversine_dist_to_station' => $distToStation, 'haversine_dist_to_dest' => $distToDestination,
            'id' => $stationId, 'lat' => $stationLat, 'lng' => $stationLng
        ];
    }

    if (empty($candidates)) {
        error_log("Nenhum candidato válido (OCM) após filtragem Haversine");
        return null;
    }

    usort($candidates, function($a, $b) { return $b['score'] <=> $a['score']; });

    // 3. Pegar top 15 e verificar distância real com Google Matrix (SOLUÇÃO 1)
    $topCandidates = array_slice($candidates, 0, 15);
    $realDistances = getGoogleMatrixDistances($currentLat, $currentLng, $topCandidates);
    
    if ($realDistances === null) {
        error_log("ERRO CRÍTICO: Google Matrix falhou. Impossível calcular distâncias reais.");
        return null; // Retorna null para o modo otimista poder pegar
    }
    
    // 4. Selecionar primeiro posto alcançável com distância real
    foreach ($topCandidates as $index => $candidate) {
        $realDistToStation = $realDistances[$index] ?? null;
        if ($realDistToStation === null) continue;
        
        // Verificação de segurança (90%)
        if ($realDistToStation <= $maxRange_km * 0.90) {
            $routeToDest = getGoogleDirections($candidate['lat'], $candidate['lng'], $destLat, $destLng);
            $realDistToDest = $routeToDest ? ($routeToDest['legs'][0]['distance']['value'] / 1000) : $candidate['haversine_dist_to_dest'];
            
            return [
                'data' => $candidate['station_data'], 'connection_info' => $candidate['connection_info'],
                'distance_to_station_km' => $realDistToStation, 'distance_to_destination_km' => $realDistToDest,
                'id' => $candidate['id'], 'lat' => $candidate['lat'], 'lng' => $candidate['lng']
            ];
        }
    }
    
    error_log("Nenhum posto (OCM) dos top 15 está dentro da autonomia real (Google Matrix)");
    return null; // << IMPORTANTE: Retorna nulo se nada for encontrado
}

/**
 * MODIFICADO: Função principal de simulação - (Aceita modo otimista)
 */
function simulateEVRoute($startLat, $startLng, $endLat, $endLng, $vehicle, $optimisticMode = false) {
    $currentLat = $startLat;
    $currentLng = $startLng;
    $currentCharge = $vehicle['initial_charge'];
    
    $chargeStops = [];
    $visitedStations = [];
    $totalDistance = 0;
    $totalChargingTime = 0;
    $totalEnergy = 0;
    
    $debugInfo = [
        'start_position' => ['lat' => $startLat, 'lng' => $startLng],
        'end_position' => ['lat' => $endLat, 'lng' => $endLng],
        'optimistic_mode' => $optimisticMode,
        'iterations' => []
    ];
    
    $maxIterations = 20;
    $iteration = 0;
    
    // Calcular distância total da rota usando Google Directions
    $initialRoute = getGoogleDirections($startLat, $startLng, $endLat, $endLng);
    if ($initialRoute === null || empty($initialRoute['legs'][0]['distance'])) {
        throw new EVSimulationException("Não foi possível calcular a rota inicial usando Google Directions.", $debugInfo);
    }
    $totalRouteDistance = $initialRoute['legs'][0]['distance']['value'] / 1000; // em KM
    
    while ($iteration < $maxIterations) {
        $iteration++;
        
        $currentRange = calculateRange($currentCharge, $vehicle);
        
        // Usar Google Directions para calcular distância real até destino
        $routeToDestination = getGoogleDirections($currentLat, $currentLng, $endLat, $endLng);
        if ($routeToDestination === null || empty($routeToDestination['legs'][0]['distance'])) {
            throw new EVSimulationException("Não foi possível calcular distância até o destino (Google Directions).", $debugInfo);
        }
        $distToDestination = $routeToDestination['legs'][0]['distance']['value'] / 1000;
        
        $iterationInfo = [ 'iteration' => $iteration, /* ... (outros logs) ... */ ];
        
        // Verificar se pode chegar ao destino
        $safeRangeToDest = $currentRange * 0.90; // (SOLUÇÃO 2: 0.95)
        if ($distToDestination <= $safeRangeToDest) { 
            $energyConsumed = ($distToDestination * $vehicle['consumption']) / 100;
            $chargeAtFinalDest = $currentCharge - (($energyConsumed / $vehicle['battery_capacity']) * 100);

            if ($chargeAtFinalDest >= $vehicle['min_charge_dest']) {
                $currentCharge = $chargeAtFinalDest;
                $totalDistance += $distToDestination;
                $totalEnergy += $energyConsumed;
                $debugInfo['iterations'][] = ['action' => 'reached_destination'];
                break; // Sucesso!
            }
            $iterationInfo['action'] = 'cannot_reach_destination_with_min_charge';
        }

        // Encontrar melhor posto usando OCM
        $bestStation = findBestChargingStation(
            $currentLat, $currentLng, $endLat, $endLng, 
            $currentRange, $visitedStations, $vehicle
        );
        
        // ===== LÓGICA DO MODO DE PLANEJAMENTO =====
        if ($bestStation === null) {
            
            if (!$optimisticMode) {
                // MODO REALISTA: Falha
                $iterationInfo['action'] = 'no_station_found_realistic_mode';
                $debugInfo['iterations'][] = $iterationInfo;
                throw new EVSimulationException(
                    "Não foi possível encontrar uma estação de recarga OCM alcançável a partir do Ponto {$iteration}. Autonomia atual: {$currentRange} km.",
                    $debugInfo
                );
            } else {
                // MODO PLANEJAMENTO: Cria um ponto simulado
                $iterationInfo['action'] = 'creating_simulated_stop';
                
                $safeDistToSimulate = $currentRange * 0.90; // (SOLUÇÃO 2: 0.95)
                
                // Calcular direção (bearing) e o novo ponto
                $bearing = getBearing($currentLat, $currentLng, $endLat, $endLng);
                $simulatedPoint = calculateSimulatedStop($currentLat, $currentLng, $bearing, $safeDistToSimulate);
                
                $distToStation = $safeDistToSimulate; // Distância é a autonomia segura
                
                // Simular viagem até o posto simulado
                $energyToStation = ($distToStation * $vehicle['consumption']) / 100;
                $chargeAtArrival = $currentCharge - (($energyToStation / $vehicle['battery_capacity']) * 100);
                
                // Abastecer (simulado)
                $chargeAtDeparture = $vehicle['max_charge_stop'];
                $energyCharged = (($chargeAtDeparture - $chargeAtArrival) / 100) * $vehicle['battery_capacity'];
                $chargingPower = $vehicle['charging_power']; // Usa potência padrão
                $chargingTime = ($energyCharged / $chargingPower) * 3600;

                // Registrar parada simulada
                $chargeStops[] = [
                    'stop_number' => count($chargeStops) + 1,
                    'distance_traveled_km' => round($totalDistance + $distToStation, 2),
                    'charge_at_arrival' => round($chargeAtArrival, 1),
                    'charge_at_departure' => round($chargeAtDeparture, 1),
                    'charge_time' => round($chargingTime),
                    'energy_charged_kwh' => round($energyCharged, 2),
                    'charging_power_kw' => $chargingPower,
                    'latitude' => $simulatedPoint['lat'],
                    'longitude' => $simulatedPoint['lng'],
                    'is_estimated' => true, // <<< FLAG IMPORTANTE
                    'station' => [
                        'id' => 'simulated_' . $iteration,
                        'name' => 'Parada Simulada (Planejamento)',
                        'address' => 'Ponto ótimo de recarga (Inexistente)',
                        'rating' => 'N/A',
                        'distance_km' => round($distToStation, 2),
                        'connector_type' => 'Simulado'
                    ]
                ];
                
                // Atualizar posição e carga
                $currentLat = $simulatedPoint['lat'];
                $currentLng = $simulatedPoint['lng'];
                $currentCharge = $chargeAtDeparture;
                $totalDistance += $distToStation;
                $totalEnergy += $energyToStation + $energyCharged;
                $totalChargingTime += $chargingTime;
                
                $iterationInfo['charge_at_departure'] = round($chargeAtDeparture, 1);
                $debugInfo['iterations'][] = $iterationInfo;

                // Continua para a próxima iteração do loop
                continue; 
            }
        }
        // ===== FIM DA LÓGICA DO MODO DE PLANEJAMENTO =====

        
        // --- Processamento de ESTAÇÃO REAL (se $bestStation não for null) ---
        $iterationInfo['action'] = 'charging_at_real_station';
        
        $distToStation = $bestStation['distance_to_station_km'];
        
        // Simular viagem até o posto
        $energyToStation = ($distToStation * $vehicle['consumption']) / 100;
        $chargeAtArrival = $currentCharge - (($energyToStation / $vehicle['battery_capacity']) * 100);
        
        // (Esta verificação é uma segurança extra, mas findBestStation já checa)
        if ($chargeAtArrival < $vehicle['min_charge_stop']) {
            $iterationInfo['action'] = 'station_too_far_min_charge';
            $debugInfo['iterations'][] = $iterationInfo;
            $visitedStations[] = $bestStation['id'];
            continue; 
        }
        
        // Abastecer
        $chargeAtDeparture = $vehicle['max_charge_stop'];
        $energyCharged = (($chargeAtDeparture - $chargeAtArrival) / 100) * $vehicle['battery_capacity'];
        $chargingPower = $bestStation['connection_info']['PowerKW'] ?? $vehicle['charging_power'];
        $chargingTime = ($energyCharged / $chargingPower) * 3600;
        
        // Registrar parada REAL
        $chargeStops[] = [
            'stop_number' => count($chargeStops) + 1,
            'distance_traveled_km' => round($totalDistance + $distToStation, 2),
            'charge_at_arrival' => round($chargeAtArrival, 1),
            'charge_at_departure' => round($chargeAtDeparture, 1),
            'charge_time' => round($chargingTime),
            'energy_charged_kwh' => round($energyCharged, 2),
            'charging_power_kw' => $chargingPower,
            'latitude' => $bestStation['lat'],
            'longitude' => $bestStation['lng'],
            'is_estimated' => false, // <<< FLAG IMPORTANTE
            'station' => [
                'id' => $bestStation['id'],
                'name' => $bestStation['data']['AddressInfo']['Title'] ?? 'Estação de Recarga',
                'address' => $bestStation['data']['AddressInfo']['AddressLine1'] ?? 'Endereço não disponível',
                'rating' => $bestStation['data']['DataQualityLevel'] ?? 'N/A',
                'distance_km' => round($distToStation, 2),
                'connector_type' => $bestStation['connection_info']['ConnectionType']['Title'] ?? 'Desconhecido'
            ]
        ];
        
        // Atualizar posição e carga
        $currentLat = $bestStation['lat'];
        $currentLng = $bestStation['lng'];
        $currentCharge = $chargeAtDeparture;
        $totalDistance += $distToStation;
        $totalEnergy += $energyToStation + $energyCharged;
        $totalChargingTime += $chargingTime;
        $visitedStations[] = $bestStation['id'];
        
        $iterationInfo['charge_at_departure'] = round($chargeAtDeparture, 1);
        $debugInfo['iterations'][] = $iterationInfo;
    }
    
    if ($iteration >= $maxIterations) {
        throw new EVSimulationException("Número máximo de paradas excedido. Rota muito longa.", $debugInfo);
    }
    
    return [
        'charge_stops' => $chargeStops,
        'total_distance_km' => round($totalDistance, 2),
        'total_charging_time_sec' => round($totalChargingTime),
        'total_energy_kwh' => round($totalEnergy, 2),
        'final_charge_pct' => round($currentCharge, 1),
        'total_route_distance' => round($totalRouteDistance, 2),
        'debug_info' => $debugInfo
    ];
}

// ===== PROCESSAMENTO PRINCIPAL =====
try {
    if (empty($_POST['start_coords']) || empty($_POST['end_coords'])) {
        throw new Exception('Coordenadas não fornecidas.');
    }
    
    list($startLat, $startLng) = array_map('floatval', explode(',', str_replace(' ', '', $_POST['start_coords'])));
    list($endLat, $endLng) = array_map('floatval', explode(',', str_replace(' ', '', $_POST['end_coords'])));
    
    // NOVO: Ler a flag do modo otimista
    $optimisticMode = isset($_POST['optimistic_mode']) && $_POST['optimistic_mode'] === 'true';

    // 1. Executar simulação (passando a flag)
    $simulation = simulateEVRoute($startLat, $startLng, $endLat, $endLng, $vehicle, $optimisticMode);
    
    // 2. Montar waypoints para rota final
    $waypointsList = [];
    $waypointsList[] = "{$startLat},{$startLng}";
    if (isset($simulation['charge_stops']) && is_array($simulation['charge_stops'])) {
        foreach ($simulation['charge_stops'] as $stop) {
            $waypointsList[] = "{$stop['latitude']},{$stop['longitude']}";
        }
    }
    $waypointsList[] = "{$endLat},{$endLng}";

    // 3. Obter geometria completa com Google Directions
    $routeData = getGoogleDirections($startLat, $startLng, $endLat, $endLng, $waypointsList);
    
    if ($routeData === null) {
        throw new Exception('Erro ao obter geometria da rota final com Google Directions.');
    }
    
    // 4. Preparar resposta
    $drivingTime = 0; $totalDistanceFromApi = 0;
    foreach ($routeData['legs'] as $leg) {
        $drivingTime += $leg['duration']['value'];
        $totalDistanceFromApi += $leg['distance']['value'];
    }
    $totalDistanceFromApi /= 1000; // em KM
    
    $totalChargingTimeSec = $simulation['total_charging_time_sec'];
    $chargeStopsDetails = $simulation['charge_stops']; // Já contém a flag 'is_estimated'
    $finalChargePct = $simulation['final_charge_pct'];
    $totalEnergyKwh = $simulation['total_energy_kwh'];
    $custo = $totalEnergyKwh * TARIFA_MEDIA_KWH;
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'geometry_polyline' => $routeData['overview_polyline']['points'],
        'bounds' => $routeData['bounds'],
        'report' => [
            'distancia_total_km' => round($totalDistanceFromApi, 2),
            'tempo_conducao_min' => round($drivingTime / 60),
            'tempo_carregamento_min' => round($totalChargingTimeSec / 60),
            'paradas_totais' => count($chargeStopsDetails),
            'energia_consumida_total_kwh' => round($totalEnergyKwh, 2),
            'custo_total_estimado' => number_format($custo, 2, ',', '.'),
            'carga_final_pct' => round($finalChargePct, 1),
            'charge_stops_details' => $chargeStopsDetails // Passa os detalhes com a flag
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (EVSimulationException $e) {
    ob_end_clean();
    error_log("EVSimulationException: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => $e->getDebugInfo()]);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>