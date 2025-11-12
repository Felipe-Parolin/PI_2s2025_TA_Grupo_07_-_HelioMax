<?php

session_start();

// Debug: Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    die('<script>alert("Você precisa estar logado para acessar o simulador!"); window.location.href = "../index.php";</script>');
}

// simulate_route.php - Versão com Suporte a Veículos Personalizados
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

if (
    !defined('GOOGLE_MAPS_API_KEY') ||
    !defined('OPEN_CHARGE_MAP_API_KEY') ||
    !defined('TARIFA_MEDIA_KWH')
) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Chaves de API não configuradas no config.php.']);
    exit;
}

if (!function_exists('curl_init')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'A extensão cURL do PHP não está habilitada.']);
    exit;
}

// Conexão com banco de dados
$host = '127.0.0.1';
$dbname = 'heliomax';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Erro ao conectar ao banco de dados: ' . $e->getMessage()]);
    exit;
}

class EVSimulationException extends Exception
{
    private $debugInfo;
    public function __construct($message, $debugInfo = [])
    {
        parent::__construct($message);
        $this->debugInfo = $debugInfo;
    }
    public function getDebugInfo()
    {
        return $this->debugInfo;
    }
}

// FUNÇÕES DE CÁLCULO
define('EARTH_RADIUS_KM', 6371);

function calculateDistance($lat1, $lng1, $lat2, $lng2)
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return EARTH_RADIUS_KM * $c;
}

function calculateRange($charge_pct, $vehicle)
{
    $energy_kwh = ($charge_pct / 100) * $vehicle['battery_capacity'];
    return ($energy_kwh / $vehicle['consumption']) * 100;
}

function getBearing($lat1, $lng1, $lat2, $lng2)
{
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    $dLng = $lng2 - $lng1;
    $y = sin($dLng) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);
    $brng = atan2($y, $x);
    return ($brng + 2 * M_PI) % (2 * M_PI);
}

function calculateSimulatedStop($lat, $lng, $bearing_rad, $distance_km)
{
    $lat = deg2rad($lat);
    $lng = deg2rad($lng);
    $d = $distance_km / EARTH_RADIUS_KM;
    $newLat = asin(sin($lat) * cos($d) + cos($lat) * sin($d) * cos($bearing_rad));
    $newLng = $lng + atan2(sin($bearing_rad) * sin($d) * cos($lat), cos($d) - sin($lat) * sin($newLat));
    return [
        'lat' => rad2deg($newLat),
        'lng' => rad2deg($newLng)
    ];
}

// FUNÇÕES GOOGLE MAPS API
function google_api_request($url, $params, $timeout = 15)
{
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
        if ($data['status'] == 'ZERO_RESULTS') {
            return $data;
        }
        error_log("Erro API Google ({$url}): Status: {$data['status']}");
        return null;
    }
    return $data;
}

function getGoogleDirections($originLat, $originLng, $destLat, $destLng, $waypointsList = [])
{
    $url = "https://maps.googleapis.com/maps/api/directions/json";
    $params = ['origin' => "{$originLat},{$originLng}", 'destination' => "{$destLat},{$destLng}", 'units' => 'metric'];
    if (!empty($waypointsList)) {
        $midpoints = array_slice($waypointsList, 1, -1);
        if (!empty($midpoints)) {
            $params['waypoints'] = 'optimize:true|' . implode('|', $midpoints);
        }
    }
    $data = google_api_request($url, $params, 30);
    if (empty($data['routes'][0])) {
        error_log("Google Directions falhou: Nenhuma rota encontrada.");
        return null;
    }
    return $data['routes'][0];
}

function getGoogleMatrixDistances($originLat, $originLng, $destinations)
{
    if (empty($destinations))
        return [];
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json";
    $originString = "{$originLat},{$originLng}";
    $destCoords = [];
    foreach ($destinations as $dest) {
        $destCoords[] = $dest['lat'] . ',' . $dest['lng'];
    }
    $destString = implode('|', $destCoords);
    $params = ['origins' => $originString, 'destinations' => $destString, 'units' => 'metric'];
    $data = google_api_request($url, $params, 30);
    if (empty($data['rows'][0]['elements'])) {
        error_log("Google Matrix falhou.");
        return null;
    }
    $distances = [];
    foreach ($data['rows'][0]['elements'] as $element) {
        if ($element['status'] === 'OK') {
            $distances[] = $element['distance']['value'] / 1000;
        } else {
            $distances[] = null;
        }
    }
    return $distances;
}

// FUNÇÃO OPENCHARGEMAP
function findOCMStations($lat, $lng, $radius_km = 100, $vehicle_connectors = [])
{
    $url = "https://api.openchargemap.io/v3/poi/";
    $params = [
        'key' => OPEN_CHARGE_MAP_API_KEY,
        'output' => 'json',
        'latitude' => $lat,
        'longitude' => $lng,
        'distance' => $radius_km,
        'distanceunit' => 'km',
        'maxresults' => 100,
        'compact' => true,
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

// BUSCAR PONTOS DO BANCO DE DADOS
function findDatabaseStations($pdo, $lat, $lng, $radius_km = 100)
{
    try {
        $sql = "SELECT 
                    pc.ID_PONTO,
                    pc.LATITUDE,
                    pc.LONGITUDE,
                    pc.VALOR_KWH,
                    pc.NUMERO,
                    pc.COMPLEMENTO,
                    c.LOGRADOURO,
                    b.NOME as BAIRRO,
                    ci.NOME as CIDADE,
                    e.UF,
                    sp.DESCRICAO as STATUS,
                    u.NOME as CADASTRADO_POR
                FROM ponto_carregamento pc
                LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
                LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                LEFT JOIN status_ponto sp ON pc.FK_STATUS_PONTO = sp.ID_STATUS_PONTO
                LEFT JOIN usuario u ON pc.FK_ID_USUARIO_CADASTRO = u.ID_USER
                WHERE pc.LATITUDE IS NOT NULL 
                AND pc.LONGITUDE IS NOT NULL
                AND pc.FK_STATUS_PONTO = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pontosNoRaio = [];

        foreach ($pontos as $ponto) {
            $distancia = calculateDistance($lat, $lng, floatval($ponto['LATITUDE']), floatval($ponto['LONGITUDE']));

            if ($distancia <= $radius_km) {
                $endereco = trim($ponto['LOGRADOURO'] ?? 'Endereço não informado');
                if (!empty($ponto['NUMERO'])) {
                    $endereco .= ', ' . $ponto['NUMERO'];
                }
                if (!empty($ponto['BAIRRO'])) {
                    $endereco .= ' - ' . $ponto['BAIRRO'];
                }
                if (!empty($ponto['CIDADE']) && !empty($ponto['UF'])) {
                    $endereco .= ', ' . $ponto['CIDADE'] . ' - ' . $ponto['UF'];
                }

                $pontosNoRaio[] = [
                    'id' => 'db_' . $ponto['ID_PONTO'],
                    'latitude' => floatval($ponto['LATITUDE']),
                    'longitude' => floatval($ponto['LONGITUDE']),
                    'name' => 'Ponto HelioMax #' . $ponto['ID_PONTO'],
                    'address' => $endereco,
                    'power_kw' => 50.0,
                    'connector_type' => 'CCS Type 2',
                    'rating' => 'Cadastrado por: ' . ($ponto['CADASTRADO_POR'] ?? 'Administrador'),
                    'distance_km' => $distancia,
                    'valor_kwh' => floatval($ponto['VALOR_KWH']),
                    'source' => 'database'
                ];
            }
        }

        error_log("Encontrados " . count($pontosNoRaio) . " pontos do banco de dados no raio de {$radius_km}km");
        return $pontosNoRaio;

    } catch (PDOException $e) {
        error_log("Erro ao buscar pontos do banco: " . $e->getMessage());
        return [];
    }
}

// MESCLAR PONTOS OCM + BANCO DE DADOS
function findBestChargingStation($pdo, $currentLat, $currentLng, $destLat, $destLng, $maxRange_km, $visitedStations, $vehicle)
{
    $searchRadius = $maxRange_km;
    $ocmStations = findOCMStations($currentLat, $currentLng, $searchRadius, $vehicle['connector_types']);
    $dbStations = findDatabaseStations($pdo, $currentLat, $currentLng, $searchRadius);

    error_log("Total de estações: " . count($ocmStations) . " (OCM) + " . count($dbStations) . " (Banco)");

    $candidates = [];

    // Processar estações da OCM
    foreach ($ocmStations as $station) {
        if (!isset($station['AddressInfo']['Latitude']) || !isset($station['AddressInfo']['Longitude']))
            continue;
        $stationId = 'ocm_' . $station['ID'];
        if (in_array($stationId, $visitedStations))
            continue;
        if (empty($station['Connections']))
            continue;

        $bestConnection = null;
        foreach ($station['Connections'] as $conn) {
            if (!empty($conn['PowerKW']) && in_array($conn['ConnectionTypeID'], $vehicle['connector_types'])) {
                if ($bestConnection == null || $conn['PowerKW'] > $bestConnection['PowerKW']) {
                    $bestConnection = $conn;
                }
            }
        }
        if ($bestConnection == null)
            continue;

        $stationLat = $station['AddressInfo']['Latitude'];
        $stationLng = $station['AddressInfo']['Longitude'];
        $distToStation = calculateDistance($currentLat, $currentLng, $stationLat, $stationLng);
        if ($distToStation < 10.0)
            continue;
        if ($distToStation > $maxRange_km)
            continue;

        $distToDestination = calculateDistance($stationLat, $stationLng, $destLat, $destLng);
        $currentDistToDest = calculateDistance($currentLat, $currentLng, $destLat, $destLng);
        $score = $currentDistToDest - $distToDestination;

        $candidates[] = [
            'score' => $score,
            'station_data' => $station,
            'connection_info' => $bestConnection,
            'haversine_dist_to_station' => $distToStation,
            'haversine_dist_to_dest' => $distToDestination,
            'id' => $stationId,
            'lat' => $stationLat,
            'lng' => $stationLng,
            'source' => 'ocm'
        ];
    }

    // Processar estações do banco de dados
    foreach ($dbStations as $dbStation) {
        $stationId = $dbStation['id'];
        if (in_array($stationId, $visitedStations))
            continue;

        $stationLat = $dbStation['latitude'];
        $stationLng = $dbStation['longitude'];
        $distToStation = $dbStation['distance_km'];

        if ($distToStation < 10.0)
            continue;
        if ($distToStation > $maxRange_km)
            continue;

        $distToDestination = calculateDistance($stationLat, $stationLng, $destLat, $destLng);
        $currentDistToDest = calculateDistance($currentLat, $currentLng, $destLat, $destLng);
        $score = $currentDistToDest - $distToDestination;

        $candidates[] = [
            'score' => $score,
            'station_data' => [
                'ID' => $stationId,
                'AddressInfo' => [
                    'Title' => $dbStation['name'],
                    'AddressLine1' => $dbStation['address'],
                    'Latitude' => $stationLat,
                    'Longitude' => $stationLng
                ],
                'DataQualityLevel' => $dbStation['rating']
            ],
            'connection_info' => [
                'PowerKW' => $dbStation['power_kw'],
                'ConnectionType' => ['Title' => $dbStation['connector_type']]
            ],
            'haversine_dist_to_station' => $distToStation,
            'haversine_dist_to_dest' => $distToDestination,
            'id' => $stationId,
            'lat' => $stationLat,
            'lng' => $stationLng,
            'source' => 'database',
            'valor_kwh' => $dbStation['valor_kwh']
        ];
    }

    if (empty($candidates)) {
        error_log("Nenhum candidato válido após filtragem Haversine");
        return null;
    }

    usort($candidates, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $topCandidates = array_slice($candidates, 0, 15);
    $realDistances = getGoogleMatrixDistances($currentLat, $currentLng, $topCandidates);

    if ($realDistances === null) {
        error_log("ERRO CRÍTICO: Google Matrix falhou.");
        return null;
    }

    foreach ($topCandidates as $index => $candidate) {
        $realDistToStation = $realDistances[$index] ?? null;
        if ($realDistToStation === null)
            continue;

        if ($realDistToStation <= $maxRange_km * 0.90) {
            $routeToDest = getGoogleDirections($candidate['lat'], $candidate['lng'], $destLat, $destLng);
            $realDistToDest = $routeToDest ? ($routeToDest['legs'][0]['distance']['value'] / 1000) : $candidate['haversine_dist_to_dest'];

            return [
                'data' => $candidate['station_data'],
                'connection_info' => $candidate['connection_info'],
                'distance_to_station_km' => $realDistToStation,
                'distance_to_destination_km' => $realDistToDest,
                'id' => $candidate['id'],
                'lat' => $candidate['lat'],
                'lng' => $candidate['lng'],
                'source' => $candidate['source'],
                'valor_kwh' => $candidate['valor_kwh'] ?? null
            ];
        }
    }

    error_log("Nenhum posto dos top 15 está dentro da autonomia real");
    return null;
}

// FUNÇÃO PRINCIPAL DE SIMULAÇÃO
function simulateEVRoute($pdo, $startLat, $startLng, $endLat, $endLng, $vehicle, $optimisticMode = false)
{
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
        'vehicle_info' => [
            'name' => $vehicle['name'],
            'battery_capacity' => $vehicle['battery_capacity'],
            'consumption' => $vehicle['consumption']
        ],
        'iterations' => []
    ];

    $maxIterations = 20;
    $iteration = 0;

    $initialRoute = getGoogleDirections($startLat, $startLng, $endLat, $endLng);
    if ($initialRoute === null || empty($initialRoute['legs'][0]['distance'])) {
        throw new EVSimulationException("Não foi possível calcular a rota inicial.", $debugInfo);
    }
    $totalRouteDistance = $initialRoute['legs'][0]['distance']['value'] / 1000;

    while ($iteration < $maxIterations) {
        $iteration++;

        $currentRange = calculateRange($currentCharge, $vehicle);

        $routeToDestination = getGoogleDirections($currentLat, $currentLng, $endLat, $endLng);
        if ($routeToDestination === null || empty($routeToDestination['legs'][0]['distance'])) {
            throw new EVSimulationException("Não foi possível calcular distância até o destino.", $debugInfo);
        }
        $distToDestination = $routeToDestination['legs'][0]['distance']['value'] / 1000;

        $iterationInfo = ['iteration' => $iteration];

        $safeRangeToDest = $currentRange * 0.90;
        if ($distToDestination <= $safeRangeToDest) {
            $energyConsumed = ($distToDestination * $vehicle['consumption']) / 100;
            $chargeAtFinalDest = $currentCharge - (($energyConsumed / $vehicle['battery_capacity']) * 100);

            if ($chargeAtFinalDest >= $vehicle['min_charge_dest']) {
                $currentCharge = $chargeAtFinalDest;
                $totalDistance += $distToDestination;
                $totalEnergy += $energyConsumed;
                $debugInfo['iterations'][] = ['action' => 'reached_destination'];
                break;
            }
            $iterationInfo['action'] = 'cannot_reach_destination_with_min_charge';
        }

        $bestStation = findBestChargingStation(
            $pdo,
            $currentLat,
            $currentLng,
            $endLat,
            $endLng,
            $currentRange,
            $visitedStations,
            $vehicle
        );

        if ($bestStation === null) {
            if (!$optimisticMode) {
                $iterationInfo['action'] = 'no_station_found_realistic_mode';
                $debugInfo['iterations'][] = $iterationInfo;
                throw new EVSimulationException(
                    "Não foi possível encontrar uma estação de recarga alcançável. Autonomia atual: {$currentRange} km.",
                    $debugInfo
                );
            } else {
                $iterationInfo['action'] = 'creating_simulated_stop';

                $safeDistToSimulate = $currentRange * 0.90;
                $bearing = getBearing($currentLat, $currentLng, $endLat, $endLng);
                $simulatedPoint = calculateSimulatedStop($currentLat, $currentLng, $bearing, $safeDistToSimulate);

                $distToStation = $safeDistToSimulate;
                $energyToStation = ($distToStation * $vehicle['consumption']) / 100;
                $chargeAtArrival = $currentCharge - (($energyToStation / $vehicle['battery_capacity']) * 100);

                $chargeAtDeparture = $vehicle['max_charge_stop'];
                $energyCharged = (($chargeAtDeparture - $chargeAtArrival) / 100) * $vehicle['battery_capacity'];
                $chargingPower = $vehicle['charging_power'];
                $chargingTime = ($energyCharged / $chargingPower) * 3600;

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
                    'is_estimated' => true,
                    'station' => [
                        'id' => 'simulated_' . $iteration,
                        'name' => 'Parada Simulada (Planejamento)',
                        'address' => 'Ponto ótimo de recarga (Inexistente)',
                        'rating' => 'N/A',
                        'distance_km' => round($distToStation, 2),
                        'connector_type' => 'Simulado'
                    ]
                ];

                $currentLat = $simulatedPoint['lat'];
                $currentLng = $simulatedPoint['lng'];
                $currentCharge = $chargeAtDeparture;
                $totalDistance += $distToStation;
                $totalEnergy += $energyToStation + $energyCharged;
                $totalChargingTime += $chargingTime;

                $iterationInfo['charge_at_departure'] = round($chargeAtDeparture, 1);
                $debugInfo['iterations'][] = $iterationInfo;

                continue;
            }
        }

        $iterationInfo['action'] = 'charging_at_real_station';
        $iterationInfo['station_source'] = $bestStation['source'];

        $distToStation = $bestStation['distance_to_station_km'];
        $energyToStation = ($distToStation * $vehicle['consumption']) / 100;
        $chargeAtArrival = $currentCharge - (($energyToStation / $vehicle['battery_capacity']) * 100);

        if ($chargeAtArrival < $vehicle['min_charge_stop']) {
            $iterationInfo['action'] = 'station_too_far_min_charge';
            $debugInfo['iterations'][] = $iterationInfo;
            $visitedStations[] = $bestStation['id'];
            continue;
        }

        $chargeAtDeparture = $vehicle['max_charge_stop'];
        $energyCharged = (($chargeAtDeparture - $chargeAtArrival) / 100) * $vehicle['battery_capacity'];
        $chargingPower = $bestStation['connection_info']['PowerKW'] ?? $vehicle['charging_power'];
        $chargingTime = ($energyCharged / $chargingPower) * 3600;

        $isFromDatabase = ($bestStation['source'] === 'database');

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
            'is_estimated' => false,
            'is_from_database' => $isFromDatabase,
            'station' => [
                'id' => $bestStation['id'],
                'name' => $bestStation['data']['AddressInfo']['Title'] ?? 'Estação de Recarga',
                'address' => $bestStation['data']['AddressInfo']['AddressLine1'] ?? 'Endereço não disponível',
                'rating' => $bestStation['data']['DataQualityLevel'] ?? 'N/A',
                'distance_km' => round($distToStation, 2),
                'connector_type' => $bestStation['connection_info']['ConnectionType']['Title'] ?? 'Desconhecido',
                'source' => $bestStation['source']
            ]
        ];

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
        throw new EVSimulationException("Número máximo de paradas excedido.", $debugInfo);
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

    $optimisticMode = isset($_POST['optimistic_mode']) && $_POST['optimistic_mode'] === 'true';

    // NOVA LÓGICA: Verificar se um veículo foi selecionado
    if (!empty($_POST['vehicle_id']) && $_POST['vehicle_id'] !== 'default') {
        // Buscar dados do veículo no banco
        $vehicleId = intval($_POST['vehicle_id']);
        $sql = "SELECT 
                    v.NIVEL_BATERIA,
                    m.CAPACIDADE_BATERIA,
                    m.CONSUMO_MEDIO,
                    m.NOME as MODELO_NOME,
                    ma.NOME as MARCA_NOME
                FROM veiculo v
                INNER JOIN modelo m ON v.MODELO = m.ID_MODELO
                INNER JOIN marca ma ON m.FK_MARCA = ma.ID_MARCA
                WHERE v.ID_VEICULO = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vehicleId]);
        $vehicleData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicleData) {
            throw new Exception('Veículo não encontrado.');
        }

        // Configurar veículo com dados reais do banco
        $vehicle = [
            'name' => $vehicleData['MARCA_NOME'] . ' ' . $vehicleData['MODELO_NOME'],
            'battery_capacity' => floatval($vehicleData['CAPACIDADE_BATERIA']),
            'initial_charge' => floatval($vehicleData['NIVEL_BATERIA']),
            'consumption' => floatval($vehicleData['CONSUMO_MEDIO']),
            'min_charge_stop' => 8.0,
            'max_charge_stop' => 100.0,
            'min_charge_dest' => 10.0,
            'charging_power' => 50.0,
            'connector_types' => [1036, 33] // CCS e CHAdeMO
        ];

        error_log("Usando veículo do usuário: {$vehicle['name']} - Bateria: {$vehicle['battery_capacity']}kWh, Consumo: {$vehicle['consumption']}kWh/100km");

    } else {
        // Usar veículo padrão (Tesla Model 3)
        $vehicle = [
            'name' => 'Tesla Model 3 (Pré-definido)',
            'battery_capacity' => 75.0,
            'initial_charge' => 95.0,
            'consumption' => 17.0,
            'min_charge_stop' => 8.0,
            'max_charge_stop' => 100.0,
            'min_charge_dest' => 10.0,
            'charging_power' => 50.0,
            'connector_types' => [1036, 33]
        ];

        error_log("Usando veículo padrão: Tesla Model 3");
    }

    // Executar simulação
    $simulation = simulateEVRoute($pdo, $startLat, $startLng, $endLat, $endLng, $vehicle, $optimisticMode);

    // Montar waypoints para rota final
    $waypointsList = [];
    $waypointsList[] = "{$startLat},{$startLng}";
    if (isset($simulation['charge_stops']) && is_array($simulation['charge_stops'])) {
        foreach ($simulation['charge_stops'] as $stop) {
            $waypointsList[] = "{$stop['latitude']},{$stop['longitude']}";
        }
    }
    $waypointsList[] = "{$endLat},{$endLng}";

    // Obter geometria completa
    $routeData = getGoogleDirections($startLat, $startLng, $endLat, $endLng, $waypointsList);

    if ($routeData === null) {
        throw new Exception('Erro ao obter geometria da rota final.');
    }

    // Preparar resposta
    $drivingTime = 0;
    $totalDistanceFromApi = 0;
    foreach ($routeData['legs'] as $leg) {
        $drivingTime += $leg['duration']['value'];
        $totalDistanceFromApi += $leg['distance']['value'];
    }
    $totalDistanceFromApi /= 1000;

    $totalChargingTimeSec = $simulation['total_charging_time_sec'];
    $chargeStopsDetails = $simulation['charge_stops'];
    $finalChargePct = $simulation['final_charge_pct'];
    $totalEnergyKwh = $simulation['total_energy_kwh'];
    $custo = $totalEnergyKwh * TARIFA_MEDIA_KWH;

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'geometry_polyline' => $routeData['overview_polyline']['points'],
        'bounds' => $routeData['bounds'],
        'vehicle_info' => [
            'name' => $vehicle['name'],
            'battery_capacity' => $vehicle['battery_capacity'],
            'consumption' => $vehicle['consumption'],
            'initial_charge' => $vehicle['initial_charge']
        ],
        'report' => [
            'distancia_total_km' => round($totalDistanceFromApi, 2),
            'tempo_conducao_min' => round($drivingTime / 60),
            'tempo_carregamento_min' => round($totalChargingTimeSec / 60),
            'paradas_totais' => count($chargeStopsDetails),
            'energia_consumida_total_kwh' => round($totalEnergyKwh, 2),
            'custo_total_estimado' => number_format($custo, 2, ',', '.'),
            'carga_final_pct' => round($finalChargePct, 1),
            'charge_stops_details' => $chargeStopsDetails
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