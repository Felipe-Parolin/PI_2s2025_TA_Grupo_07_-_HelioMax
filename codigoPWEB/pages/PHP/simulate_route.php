<?php

session_start();

// Debug: Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    die('<script>alert("Você precisa estar logado para acessar o simulador!"); window.location.href = "../index.php";</script>');
}

// simulate_route.php - Versão CORRIGIDA para Salvar Nomes de Paradas e PDF Igual
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
    $params = [
        'origin' => "{$originLat},{$originLng}",
        'destination' => "{$destLat},{$destLng}",
        'units' => 'metric'
    ];

    if (!empty($waypointsList)) {
        $midpoints = array_slice($waypointsList, 1, -1);
        if (!empty($midpoints)) {
            // optimize:false para respeitar a ordem
            $params['waypoints'] = 'optimize:false|' . implode('|', $midpoints);
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
                    u.NOME as CADASTRADO_POR,
                    COALESCE(AVG(a.NOTA), 0) as MEDIA_NOTA
                FROM ponto_carregamento pc
                LEFT JOIN cep c ON pc.LOCALIZACAO = c.ID_CEP
                LEFT JOIN bairro b ON c.FK_BAIRRO = b.ID_BAIRRO
                LEFT JOIN cidade ci ON b.FK_CIDADE = ci.ID_CIDADE
                LEFT JOIN estado e ON ci.FK_ESTADO = e.ID_ESTADO
                LEFT JOIN status_ponto sp ON pc.FK_STATUS_PONTO = sp.ID_STATUS_PONTO
                LEFT JOIN usuario u ON pc.FK_ID_USUARIO_CADASTRO = u.ID_USER
                LEFT JOIN avaliacao a ON a.FK_PONTO_CARRRGAMENTO = pc.ID_PONTO 
                WHERE pc.LATITUDE IS NOT NULL 
                AND pc.LONGITUDE IS NOT NULL
                AND pc.FK_STATUS_PONTO = 1
                GROUP BY pc.ID_PONTO";

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

                $notaFormatada = (floatval($ponto['MEDIA_NOTA']) > 0)
                    ? number_format($ponto['MEDIA_NOTA'], 1) . ' / 5.0'
                    : 'Sem avaliações';

                $pontosNoRaio[] = [
                    'id' => 'db_' . $ponto['ID_PONTO'],
                    'latitude' => floatval($ponto['LATITUDE']),
                    'longitude' => floatval($ponto['LONGITUDE']),
                    'name' => 'Ponto HelioMax #' . $ponto['ID_PONTO'],
                    'address' => $endereco,
                    'power_kw' => 50.0,
                    'connector_type' => 'CCS Type 2',
                    'rating' => $notaFormatada,
                    'distance_km' => $distancia,
                    'valor_kwh' => floatval($ponto['VALOR_KWH']),
                    'source' => 'database'
                ];
            }
        }
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

        $iterationInfo['charge_at_departure'] = round($currentCharge, 1);
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

// ==================== FUNÇÕES DE HISTÓRICO ====================

function geocodeAddress($lat, $lng)
{
    if (!defined('GOOGLE_MAPS_API_KEY')) {
        return "Lat: $lat, Lng: $lng (API Key não definida)";
    }

    $url = "https://maps.googleapis.com/maps/api/geocode/json";
    $params = [
        'latlng' => "$lat,$lng",
        'key' => GOOGLE_MAPS_API_KEY,
        'language' => 'pt-BR'
    ];

    $queryString = http_build_query($params);
    $apiUrl = $url . '?' . $queryString;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpcode === 200) {
        $data = json_decode($response, true);
        if ($data['status'] === 'OK' && !empty($data['results'][0])) {
            return $data['results'][0]['formatted_address'];
        }
    }

    return "Lat: $lat, Lng: $lng";
}

function salvarNoHistorico(
    $pdo,
    $userId,
    $vehicleId,
    $startLat,
    $startLng,
    $endLat,
    $endLng,
    $simulation,
    $optimisticMode,
    $polyline,
    $drivingTimeSec,
    $totalDistanceFromApi,
    $manualStopovers
) {
    try {
        $origemEndereco = geocodeAddress($startLat, $startLng);
        $destinoEndereco = geocodeAddress($endLat, $endLng);

        $custo = $simulation['total_energy_kwh'] * TARIFA_MEDIA_KWH;

        $allStops = [
            'manual_stops' => $manualStopovers,
            'charge_stops' => $simulation['charge_stops']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO historico_rota (
                FK_USUARIO, FK_VEICULO, ORIGEM_LAT, ORIGEM_LNG, ORIGEM_ENDERECO,
                DESTINO_LAT, DESTINO_LNG, DESTINO_ENDERECO, DISTANCIA_TOTAL_KM,
                TEMPO_CONDUCAO_MIN, TEMPO_CARREGAMENTO_MIN, PARADAS_TOTAIS,
                ENERGIA_CONSUMIDA_KWH, CUSTO_TOTAL, CARGA_FINAL_PCT,
                MODO_OTIMISTA, DADOS_PARADAS, POLYLINE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $vehicleId,
            $startLat,
            $startLng,
            $origemEndereco,
            $endLat,
            $endLng,
            $destinoEndereco,
            round($totalDistanceFromApi, 2),
            round($drivingTimeSec / 60),
            round($simulation['total_charging_time_sec'] / 60),
            count($simulation['charge_stops']) + count($manualStopovers),
            $simulation['total_energy_kwh'],
            round($custo, 2),
            $simulation['final_charge_pct'],
            $optimisticMode ? 1 : 0,
            json_encode($allStops, JSON_UNESCAPED_UNICODE),
            $polyline
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Erro ao salvar histórico: " . $e->getMessage());
        return false;
    }
}

// ===== PROCESSAMENTO PRINCIPAL =====
try {
    if (empty($_POST['start_coords']) || empty($_POST['end_coords'])) {
        throw new Exception('Coordenadas de origem ou destino não fornecidas.');
    }

    list($startLat, $startLng) = array_map('floatval', explode(',', str_replace(' ', '', $_POST['start_coords'])));
    list($endLat, $endLng) = array_map('floatval', explode(',', str_replace(' ', '', $_POST['end_coords'])));

    $optimisticMode = isset($_POST['optimistic_mode']) && $_POST['optimistic_mode'] === 'true';

    // *** COLETAR E PARSEAR MÚLTIPLAS PARADAS MANUAIS COM NOME/ENDEREÇO ***
    $manualStopovers = [];
    if (!empty($_POST['stopovers_data'])) {
        // Agora espera um JSON String completo: [{lat, lng, name}, ...]
        $decodedStops = json_decode($_POST['stopovers_data'], true);

        if (is_array($decodedStops)) {
            foreach ($decodedStops as $index => $stop) {
                if (isset($stop['lat']) && isset($stop['lng'])) {
                    // Se o nome não vier do front, faz um fallback
                    $name = !empty($stop['name']) ? $stop['name'] : geocodeAddress($stop['lat'], $stop['lng']);

                    $manualStopovers[] = [
                        'lat' => floatval($stop['lat']),
                        'lng' => floatval($stop['lng']),
                        'type' => 'manual',
                        'name' => $name
                    ];
                }
            }
        }
    } elseif (!empty($_POST['stopover_coords_list'])) {
        // Fallback para o método antigo (apenas coordenadas)
        $stopoverStrings = explode(';', $_POST['stopover_coords_list']);
        foreach ($stopoverStrings as $index => $stopCoordString) {
            $coords = array_map('floatval', explode(',', trim($stopCoordString)));
            if (count($coords) === 2) {
                $manualStopovers[] = [
                    'lat' => $coords[0],
                    'lng' => $coords[1],
                    'type' => 'manual',
                    'name' => 'Parada Manual #' . ($index + 1)
                ];
            }
        }
    }

    // Seleção e Configuração do Veículo
    $vehicleIdForHistory = null;
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

    if (!empty($_POST['vehicle_id']) && $_POST['vehicle_id'] !== 'default') {
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

        if ($vehicleData) {
            $vehicleIdForHistory = $vehicleId;
            $vehicle['name'] = $vehicleData['MARCA_NOME'] . ' ' . $vehicleData['MODELO_NOME'];
            $vehicle['battery_capacity'] = floatval($vehicleData['CAPACIDADE_BATERIA']);
            $vehicle['initial_charge'] = floatval($vehicleData['NIVEL_BATERIA']);
            $vehicle['consumption'] = floatval($vehicleData['CONSUMO_MEDIO']);
            error_log("Usando veículo do usuário: {$vehicle['name']}");
        } else {
            error_log("Veículo ID {$vehicleId} não encontrado. Usando padrão.");
        }
    } else {
        error_log("Usando veículo padrão: Tesla Model 3");
    }

    // Executar simulação
    $simulation = simulateEVRoute($pdo, $startLat, $startLng, $endLat, $endLng, $vehicle, $optimisticMode);

    // *** MONTAR WAYPOINTS ORDENADOS POR DISTÂNCIA PROGRESSIVA ***
    $allWaypoints = [];

    // Adicionar paradas manuais com tipo e coordenadas
    foreach ($manualStopovers as $stop) {
        $allWaypoints[] = [
            'lat' => $stop['lat'],
            'lng' => $stop['lng'],
            'type' => 'manual',
            'data' => $stop,
            'distance_from_start' => calculateDistance($startLat, $startLng, $stop['lat'], $stop['lng'])
        ];
    }

    // Adicionar paradas de recarga
    if (isset($simulation['charge_stops']) && is_array($simulation['charge_stops'])) {
        foreach ($simulation['charge_stops'] as $stop) {
            $allWaypoints[] = [
                'lat' => $stop['latitude'],
                'lng' => $stop['longitude'],
                'type' => 'charge',
                'data' => $stop,
                'distance_from_start' => calculateDistance($startLat, $startLng, $stop['latitude'], $stop['longitude'])
            ];
        }
    }

    // *** ORDENAR TODAS AS PARADAS PELA DISTÂNCIA DA ORIGEM ***
    usort($allWaypoints, function ($a, $b) {
        return $a['distance_from_start'] <=> $b['distance_from_start'];
    });

    // Montar lista final de waypoints na ordem correta
    $waypointsList = [];
    $waypointsList[] = "{$startLat},{$startLng}"; // Origem

    foreach ($allWaypoints as $waypoint) {
        $waypointsList[] = "{$waypoint['lat']},{$waypoint['lng']}";
    }

    $waypointsList[] = "{$endLat},{$endLng}"; // Destino

    // *** OBTER GEOMETRIA COMPLETA DA ROTA (optimize:false forçado) ***
    $routeData = getGoogleDirections($startLat, $startLng, $endLat, $endLng, $waypointsList);

    if ($routeData === null) {
        throw new Exception('Erro ao obter geometria da rota final.');
    }

    // Preparar dados para resposta
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

    // *** CRIAR LISTA FINAL DE PARADAS (Manuais + Recarga) ***
    $allStopsForFrontend = [];

    // Adicionar paradas manuais
    foreach ($manualStopovers as $stop) {
        $allStopsForFrontend[] = [
            'latitude' => $stop['lat'],
            'longitude' => $stop['lng'],
            'is_manual_stop' => true,
            'name' => $stop['name']
        ];
    }

    // Adicionar paradas de recarga
    foreach ($chargeStopsDetails as $stop) {
        $allStopsForFrontend[] = $stop;
    }

    // Salvar no histórico
    salvarNoHistorico(
        $pdo,
        $_SESSION['usuario_id'],
        $vehicleIdForHistory,
        $startLat,
        $startLng,
        $endLat,
        $endLng,
        $simulation,
        $optimisticMode,
        $routeData['overview_polyline']['points'],
        $drivingTime,
        $totalDistanceFromApi,
        $manualStopovers
    );

    // *** PREPARAR E ENVIAR RESPOSTA JSON ***
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
            'paradas_totais' => count($allStopsForFrontend),
            'energia_consumida_total_kwh' => round($totalEnergyKwh, 2),
            'custo_total_estimado' => number_format($custo, 2, ',', '.'),
            'carga_final_pct' => round($finalChargePct, 1),
            'charge_stops_details' => $allStopsForFrontend
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