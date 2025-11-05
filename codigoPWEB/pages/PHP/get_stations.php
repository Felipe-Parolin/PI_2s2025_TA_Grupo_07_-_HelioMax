<?php
// get_stations.php
header('Content-Type: application/json');
include 'config.php';

// Verifica se o Bounding Box (bbox) foi enviado
if (empty($_GET['bbox'])) {
    echo json_encode(['error' => 'Parâmetro bbox ausente.']);
    exit;
}

// O bbox vem do Leaflet no formato: "sul,oeste,norte,leste"
// A OCM espera: (lat_sul, lng_oeste), (lat_norte, lng_leste)
// Precisamos formatar
$bbox = explode(',', $_GET['bbox']);
$boundingBoxParam = "({$bbox[0]},{$bbox[1]}),({$bbox[2]},{$bbox[3]})";

// Parâmetros da API Open Charge Map v3
$queryParams = http_build_query([
    'key' => OPEN_CHARGE_MAP_API_KEY,
    'output' => 'json',
    'boundingbox' => $boundingBoxParam,
    'maxresults' => 100, // Limitar para não sobrecarregar
    'compact' => true,   // Menos dados, mais rápido
    'distanceunit' => 'km'
]);

$apiUrl = "https://api.openchargemap.io/v3/poi/?" . $queryParams;

// Usar cURL para buscar os dados
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
// É importante definir um User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, 'SeuAppSimuladorEV/1.0 (https://seusite.com)'); 
$response = curl_exec($ch);
curl_close($ch);

// Retorna a resposta da OCM diretamente para o JavaScript
echo $response;
?>