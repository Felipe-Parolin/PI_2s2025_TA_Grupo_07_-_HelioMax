<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/icon.png"> <title>Simulador de Rota EV</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/@mapbox/polyline@1.1.1/src/polyline.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        /* ... (seu CSS style.css permanece o mesmo) ... */
        /* Estilo para o switch/toggle */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #4b5563; -webkit-transition: .4s; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; -webkit-transition: .4s; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #22d3ee; }
        input:checked + .slider:before { -webkit-transform: translateX(20px); -ms-transform: translateX(20px); transform: translateX(20px); }
        
        /* Z-index para o dropdown do Google Autocomplete aparecer sobre o mapa */
        .pac-container {
            z-index: 10000 !important;
            background-color: #1f2937; /* slate-800 */
            border: 1px solid #06b6d4; /* cyan-500 */
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .pac-item {
            color: #d1d5db; /* gray-300 */
            padding: 10px;
            cursor: pointer;
            border-top: 1px solid #374151; /* slate-700 */
        }
        .pac-item:first-child {
            border-top: none;
        }
        .pac-item:hover, .pac-item-selected {
            background-color: #334155; /* slate-700 */
        }
        .pac-item-query {
            color: #f8fafc; /* slate-50 */
            font-weight: 600;
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white flex">

    <div class="flex flex-1 h-screen">

        <div class="w-full max-w-sm bg-slate-900/50 backdrop-blur-xl border-r border-cyan-500/20 p-6 flex flex-col space-y-6 overflow-y-auto">
            
            <div class="flex items-center gap-3 border-b border-cyan-500/20 pb-4">
                <div
                    class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i data-lucide="zap" class="w-7 h-7 text-white"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">Simulador de Rota EV</h2>
                    <p class="text-xs text-gray-400">Veículo: <strong class="text-cyan-400">Tesla Model 3 (Pré-definido)</strong></p>
                </div>
            </div>


            <div class="space-y-4">
                <div class="relative">
                    <i data-lucide="map-pin" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="text" id="start-point" placeholder="Digite a Origem ou clique no mapa"
                        class="w-full pl-10 pr-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                </div>
                <div class="relative">
                    <i data-lucide="flag" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="text" id="end-point" placeholder="Digite o Destino ou clique no mapa"
                        class="w-full pl-10 pr-4 py-2 bg-slate-900/50 border border-cyan-500/20 rounded-xl placeholder-gray-500 text-white focus:ring-cyan-500 focus:border-cyan-500 transition-colors">
                </div>
                
                <button id="simulate-button"
                    class="w-full bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-300">
                    <i data-lucide="route" class="w-5 h-5"></i>
                    <span>Simular Rota</span>
                </button>
                <button id="clear-button"
                    class="w-full bg-slate-700/50 hover:bg-slate-600/50 border border-cyan-500/20 text-gray-300 font-bold py-3 px-6 rounded-xl flex items-center justify-center gap-2 transition-all duration-300">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                    <span>Limpar</span>
                </button>
            </div>

            <hr class="border-t border-cyan-500/20">
            <div class="flex items-center justify-between p-3 bg-slate-800/50 border border-cyan-500/20 rounded-xl">
                <div class="flex-1">
                    <label for="optimistic-mode" class="font-medium text-sm text-cyan-400">Modo Planejamento</label>
                    <p class="text-xs text-gray-400">Criar paradas simuladas se postos reais não forem encontrados.</p>
                </div>
                <label class="switch flex-shrink-0 ml-3">
                    <input type="checkbox" id="optimistic-mode">
                    <span class="slider"></span>
                </label>
            </div>
            <button id="download-pdf-button" 
                style="display: none;"
                class="w-full my-3 p-2 rounded-lg bg-cyan-600 text-white font-bold flex items-center justify-center gap-2 hover:bg-cyan-500 transition-colors duration-200">
                <i data-lucide="download" class="w-4 h-4"></i>
                Baixar Relatório (PDF)
            </button>


            <div id="report-section" class="flex flex-col flex-grow bg-slate-800/50 p-4 rounded-xl border border-cyan-500/20">
                <h3 class="text-xl font-bold mb-3 text-cyan-400 flex items-center gap-2"><i data-lucide="clipboard-list" class="w-5 h-5"></i> Relatório da Viagem</h3>
                <div id="report-content" class="text-gray-300 space-y-2 text-sm flex-grow">
                    <div id="report-content" class="text-sm space-y-2">
                        <p>Aguardando simulação...</p>
                    </div>
                </div>
            </div>

            <div id="loading-spinner" class="spinner" style="display: none;"></div>
        </div>

        <div id="map" class="flex-1 h-full rounded-l-none rounded-2xl">
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD8GxprFa1NCA_pfGzXQqC6Eiflx7BeEKY&libraries=places"></script>

    <script src="../JS/simulador.js"></script>
</body>

</html>