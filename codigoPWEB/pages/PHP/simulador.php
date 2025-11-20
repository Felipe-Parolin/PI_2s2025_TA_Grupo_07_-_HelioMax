<?php
session_start();

// Debug: Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    die('<script>alert("Você precisa estar logado para acessar o simulador!"); window.location.href = "../index.php";</script>');
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/icon.png">
    <title>Simulador de Rota EV</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #4b5563;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #22d3ee;
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #22d3ee;
        }

        input:checked+.slider:before {
            -webkit-transform: translateX(20px);
            -ms-transform: translateX(20px);
            transform: translateX(20px);
        }

        .select-favorite:hover {
            border-color: #22d3ee;
            box-shadow: 0 0 5px rgba(34, 211, 238, 0.5);
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #22d3ee;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .pac-container {
            z-index: 10000 !important;
            background-color: #1f2937;
            border: 1px solid #06b6d4;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .hidden {
            display: none !important;
        }

        .pac-item {
            color: #d1d5db;
            padding: 10px;
            cursor: pointer;
            border-top: 1px solid #374151;
        }

        .pac-item:first-child {
            border-top: none;
        }

        .pac-item:hover,
        .pac-item-selected {
            background-color: #334155;
        }

        .pac-item-query {
            color: #f8fafc;
            font-weight: 600;
        }

        .max-h-64::-webkit-scrollbar {
            width: 6px;
        }

        .max-h-64::-webkit-scrollbar-track {
            background: #1e293b;
            border-radius: 10px;
        }

        .max-h-64::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 10px;
        }

        .max-h-64::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
</head>

<body class="bg-gray-900 text-white min-h-screen">

    <div class="flex h-screen p-4 gap-4">

        <div class="sidebar w-96 flex flex-col bg-slate-800/80 p-6 rounded-2xl shadow-2xl backdrop-blur-sm border border-cyan-500/10 overflow-y-auto">
            <div class="flex items-center gap-3 border-b border-cyan-500/20 pb-4 mb-4">
                <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i data-lucide="zap" class="w-7 h-7 text-white"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">Simulador de Rota EV</h2>
                    <p class="text-xs text-gray-400" id="vehicle-display-text">Selecione seu veículo</p>
                </div>
            </div>

            <!-- SELETOR DE VEÍCULO -->
            <div class="mb-6 p-4 bg-slate-900/50 rounded-xl border border-cyan-500/20">
                <label for="vehicle-select" class="block text-sm font-medium text-cyan-300 mb-2 flex items-center gap-2">
                    <i data-lucide="car" class="w-4 h-4"></i>
                    Selecione seu Veículo
                </label>
                <select id="vehicle-select" class="w-full pl-4 pr-4 py-2.5 bg-slate-800 border border-cyan-500/30 rounded-xl text-white focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                    <option value="default">Tesla Model 3 (Padrão)</option>
                </select>

                <!-- Informações do Veículo Selecionado -->
                <div id="vehicle-info" class="mt-3 p-3 bg-slate-800/50 rounded-lg border border-cyan-500/10 hidden">
                    <div class="text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Bateria:</span>
                            <span class="text-cyan-400 font-semibold" id="vehicle-battery">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Consumo:</span>
                            <span class="text-cyan-400 font-semibold" id="vehicle-consumption">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Nível Atual:</span>
                            <span class="text-cyan-400 font-semibold" id="vehicle-charge">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Autonomia:</span>
                            <span class="text-green-400 font-semibold" id="vehicle-range">-</span>
                        </div>
                    </div>
                </div>

                <!-- Link para cadastrar veículo -->
                <a href="veiculos.php" target="_blank" class="mt-2 text-xs text-cyan-400 hover:text-cyan-300 flex items-center gap-1 transition-colors">
                    <i data-lucide="plus-circle" class="w-3 h-3"></i>
                    Não tem veículo? Cadastre aqui
                </a>
            </div>

            <div class="inputs space-y-4 mb-6">

                <!-- PONTO DE PARTIDA -->
                <div>
                    <label for="start-point" class="block text-sm font-medium text-gray-300 mb-1">Ponto de Partida (Origem)</label>
                    <div class="relative">
                        <i data-lucide="map-pin" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="start-point" placeholder="Digite a Origem ou clique no mapa" class="w-full pl-10 pr-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                    </div>

                    <div id="start-favorite-control" class="mt-2" style="display: block;">
                        <label for="start-favorite-select" class="text-xs font-semibold text-gray-400">Ou use um Favorito:</label>
                        <select id="start-favorite-select" class="w-full pl-4 pr-4 py-2 bg-slate-900/50 border border-yellow-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-yellow-500 focus:border-yellow-500 transition-colors select-favorite text-sm">
                            <option value="">-- Selecione um Favorito --</option>
                        </select>
                    </div>
                </div>

                <!-- SEÇÃO DE PARADAS MÚLTIPLAS -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-yellow-300 flex items-center gap-2">
                            <i data-lucide="flag" class="w-4 h-4"></i>
                            Pontos de Parada (Opcional)
                        </label>
                        <button id="add-stopover-btn" class="text-xs text-yellow-400 hover:text-yellow-300 flex items-center gap-1 transition-colors">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            Adicionar
                        </button>
                    </div>

                    <!-- Container de Paradas -->
                    <div id="stopovers-container" class="space-y-2 max-h-64 overflow-y-auto pr-2">
                        <!-- Paradas serão adicionadas aqui dinamicamente -->
                    </div>

                    <p class="text-xs text-gray-500 mt-2">
                        <i data-lucide="info" class="w-3 h-3 inline"></i>
                        Adicione paradas intermediárias para personalizar sua rota
                    </p>
                </div>

                <!-- PONTO DE CHEGADA -->
                <div>
                    <label for="end-point" class="block text-sm font-medium text-gray-300 mb-1">Ponto de Chegada (Destino)</label>
                    <div class="relative">
                        <i data-lucide="flag" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="end-point" placeholder="Digite o Destino ou clique no mapa" class="w-full pl-10 pr-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                    </div>

                    <div id="end-favorite-control" class="mt-2" style="display: block;">
                        <label for="end-favorite-select" class="text-xs font-semibold text-gray-400">Ou use um Favorito:</label>
                        <select id="end-favorite-select" class="w-full pl-4 pr-4 py-2 bg-slate-900/50 border border-yellow-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-yellow-500 focus:border-yellow-500 transition-colors select-favorite text-sm">
                            <option value="">-- Selecione um Favorito --</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between mb-6 pb-4 border-b border-cyan-500/20">
                <span class="text-sm font-medium text-gray-300">Modo Planejamento (Simular Paradas)</span>
                <label class="switch">
                    <input type="checkbox" id="optimistic-mode" checked>
                    <span class="slider round"></span>
                </label>
            </div>

            <div class="flex gap-2 mb-6">
                <button id="simulate-button" class="flex-1 bg-cyan-600 hover:bg-cyan-500 text-white font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 transition-all duration-300 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50">
                    <i data-lucide="play" class="w-5 h-5"></i>
                    <span>Simular Rota</span>
                </button>
                <button id="clear-button" class="bg-slate-700/50 hover:bg-slate-600/50 border border-cyan-500/20 text-gray-300 font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 transition-all duration-300">
                    <i data-lucide="x" class="w-5 h-5"></i>
                    <span>Limpar</span>
                </button>
            </div>

            <button id="download-pdf-button" style="display: none;" class="w-full my-3 p-2 rounded-lg bg-cyan-600 text-white font-bold flex items-center justify-center gap-2 hover:bg-cyan-500 transition-colors duration-200">
                <i data-lucide="download" class="w-4 h-4"></i>
                Baixar Relatório (PDF)
            </button>

            <!-- SEÇÃO DE RELATÓRIO -->
            <div id="report-section" class="flex flex-col flex-grow bg-slate-800/50 p-4 rounded-xl border border-cyan-500/20">
                <h3 class="text-xl font-bold mb-3 text-cyan-400 flex items-center gap-2">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i> Relatório da Viagem
                </h3>
                
                <!-- Container do Relatório -->
                <div id="report-content" class="text-gray-300 space-y-2 text-sm flex-grow">
                    <!-- Sumário do Relatório -->
                    <div id="report-summary">
                        <p class="text-gray-400">Aguardando simulação...</p>
                    </div>

                    <!-- Título da Lista de Paradas -->
                    <h4 id="stops-title" class="text-lg font-bold mb-2 text-cyan-400 hidden">
                        <i data-lucide="map-pin" class="w-4 h-4 inline"></i> Paradas de Recarga
                    </h4>

                    <!-- Lista de Paradas -->
                    <div id="charging-stops-list" class="space-y-2"></div>
                </div>
            </div>

            <div id="loading-spinner" class="spinner" style="display: none;"></div>
        </div>

        <div id="map" class="flex-1 h-full rounded-l-none rounded-2xl"></div>
    </div>

    <script>
        lucide.createIcons();
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD8GxprFa1NCA_pfGzXQqC6Eiflx7BeEKY&libraries=places"></script>
    <script src="../JS/simulador.js"></script>

</body>

</html>