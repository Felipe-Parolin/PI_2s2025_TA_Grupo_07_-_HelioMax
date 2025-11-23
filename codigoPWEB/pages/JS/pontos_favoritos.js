// JS/pontos_favoritos.js

document.addEventListener('DOMContentLoaded', () => {
    let map = null;
    let marker = null;
    let geocoder = null;
    let autocomplete = null;
    let currentCoords = null;

    const SEPARATOR = '|~|';

    try {
        geocoder = new google.maps.Geocoder();
    } catch (e) {
        console.error("Erro ao inicializar Google Geocoder:", e);
    }

    // --- FUNÇÕES DE ÍCONE ---

    window.selectIcon = function (iconName) {
        document.querySelectorAll('.icon-btn').forEach(btn => btn.classList.remove('active'));
        const btnClicado = document.querySelector(`.icon-btn[data-icon="${iconName}"]`);
        if (btnClicado) btnClicado.classList.add('active');
        document.getElementById('input-icone-selecionado').value = iconName;
    }

    // --- FUNÇÕES DE INTERFACE (UI) ---

    // NOVA FUNÇÃO: Garante limpeza total ao clicar em Adicionar
    window.novoFavorito = function () {
        // 1. Limpa o ID para o sistema saber que é um cadastro novo
        document.getElementById('favorito-id').value = "";

        // 2. Reseta os textos do formulário
        document.getElementById('form-favorito').reset();

        // 3. Reseta visuais (ícone e campos ocultos)
        window.selectIcon('map-pin');
        clearAddressFields();
        document.getElementById('endereco-busca').value = '';

        // 4. Limpa o mapa
        if (marker && map) {
            map.removeLayer(marker);
            marker = null;
            currentCoords = null;
        }

        // 5. Abre a janela
        window.abrirModal('modalCriarFavorito');
    }

    window.toggleSidebar = function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (sidebar) sidebar.classList.toggle('sidebar-mobile-hidden');
        if (overlay) overlay.classList.toggle('hidden');
    }

    window.abrirModal = function (id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
        }

        setTimeout(() => {
            lucide.createIcons();
            if (window.initMap) window.initMap();
        }, 100);
    }

    window.fecharModal = function (id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('active');
    }

    // --- MAPA E ENDEREÇO ---

    function extractAddressComponents(place) {
        let components = { cep: '', logradouro: '', numero: '', bairro: '', cidade: '', uf: '' };
        if (!place.address_components) return components;

        for (const component of place.address_components) {
            const types = component.types;
            if (types.includes('postal_code')) components.cep = component.long_name;
            else if (types.includes('route')) components.logradouro = component.long_name;
            else if (types.includes('street_number')) components.numero = component.long_name;
            else if (types.includes('sublocality_level_1') || types.includes('sublocality')) components.bairro = component.long_name;
            else if (types.includes('administrative_area_level_2') || types.includes('locality')) if (!components.cidade) components.cidade = component.long_name;
            else if (types.includes('administrative_area_level_1')) components.uf = component.short_name;
        }
        return components;
    }

    function updateAddressFields(components) {
        if (components.cep) document.getElementById('input-cep').value = components.cep;
        if (components.logradouro) document.getElementById('input-logradouro').value = components.logradouro;
        if (components.bairro) document.getElementById('input-bairro').value = components.bairro;
        if (components.cidade) document.getElementById('input-cidade').value = components.cidade;
        if (components.uf) document.getElementById('input-estado').value = components.uf;
        if (components.numero) document.getElementById('input-numero').value = components.numero;
    }

    function clearAddressFields() {
        document.getElementById('input-cep').value = '';
        document.getElementById('input-logradouro').value = '';
        document.getElementById('input-bairro').value = '';
        document.getElementById('input-cidade').value = '';
        document.getElementById('input-estado').value = '';
        document.getElementById('input-numero').value = '';
    }

    window.initMap = function () {
        if (!map) {
            map = L.map('map-modal').setView([-22.3755861, -47.8825], 13);
            L.tileLayer('https://maps.geoapify.com/v1/tile/klokantech-basic/{z}/{x}/{y}.png?apiKey=a94d6d7cd9de4604aea43f8e8a1d0a36', {
                attribution: 'Powered by <a href="https://www.geoapify.com/" target="_blank">Geoapify</a>',
                maxZoom: 20
            }).addTo(map);

            map.on('click', (e) => handleManualLocationSelection(e.latlng));

            const input = document.getElementById('endereco-busca');
            const options = {
                componentRestrictions: { country: 'br' },
                fields: ['formatted_address', 'geometry', 'name', 'address_components'],
            };

            autocomplete = new google.maps.places.Autocomplete(input, options);

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                const latlng = { lat: place.geometry.location.lat(), lng: place.geometry.location.lng() };
                map.setView(latlng, 16);
                setMarker(latlng);

                const components = extractAddressComponents(place);
                updateAddressFields(components);
            });
        }

        if (currentCoords && map) {
            setTimeout(() => {
                map.invalidateSize();
                map.setView(currentCoords, 16);
                setMarker(currentCoords);
            }, 200);
        }
    }

    function handleManualLocationSelection(latlng) {
        setMarker(latlng);
        const btnSalvar = document.querySelector('button[type="submit"]');
        if (btnSalvar) btnSalvar.textContent = "Buscando endereço...";

        geocoder.geocode({ 'location': latlng }, (results, status) => {
            if (btnSalvar) btnSalvar.innerHTML = '<i data-lucide="save" class="w-5 h-5 inline-block mr-2"></i> Salvar Ponto';
            lucide.createIcons();

            if (status === 'OK' && results[0]) {
                const components = extractAddressComponents(results[0]);
                updateAddressFields(components);
                if (!document.getElementById('endereco-busca').value) {
                    document.getElementById('endereco-busca').value = results[0].formatted_address;
                }
            }
        });
    }

    function setMarker(latlng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker(latlng, { draggable: true }).addTo(map);
        currentCoords = latlng;

        document.getElementById('latitude').value = latlng.lat;
        document.getElementById('longitude').value = latlng.lng;

        marker.on('dragend', (e) => handleManualLocationSelection(marker.getLatLng()));
    }

    // --- CRUD ---

    async function carregarFavoritos() {
        const listaDiv = document.getElementById('lista-favoritos');
        const emptyState = document.getElementById('empty-state');

        try {
            const response = await fetch('../PHP/api_pontos_favoritos.php');
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                listaDiv.innerHTML = '';
                emptyState.classList.add('hidden');
                listaDiv.classList.remove('hidden');

                result.data.forEach(fav => {
                    let nomeReal = fav.NOME || '';
                    let iconName = 'map-pin';

                    // Extrai o nome e ícone, removendo o separador
                    if (nomeReal && nomeReal.includes(SEPARATOR)) {
                        const parts = nomeReal.split(SEPARATOR);
                        nomeReal = parts[0].trim();
                        iconName = parts[1] ? parts[1].trim() : 'map-pin';
                    }

                    const favoritoElement = document.createElement('div');
                    favoritoElement.className = 'favorite-card bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 overflow-hidden';

                    let endereco = fav.LOGRADOURO || '';
                    if (fav.NUMERO_RESIDENCIA) endereco += `, ${fav.NUMERO_RESIDENCIA}`;
                    if (fav.bairro) endereco += ` - ${fav.bairro}`;

                    favoritoElement.innerHTML = `
                        <div class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-500/30">
                                    <i data-lucide="${iconName}" class="w-6 h-6 text-white"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-xl font-bold text-white mb-1 truncate" title="${nomeReal}">${nomeReal}</h3>
                                    <p class="text-gray-400 text-sm leading-relaxed">${endereco}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 border-t border-cyan-500/20">
                            <button onclick="editarFavorito(${fav.ID_PONTO_INTERESSE})" class="py-3 px-4 text-cyan-400 hover:bg-cyan-500/10 border-r border-cyan-500/20 flex justify-center items-center gap-2">
                                <i data-lucide="pencil" class="w-4 h-4"></i> Editar
                            </button>
                            <button onclick="excluirFavorito(${fav.ID_PONTO_INTERESSE})" class="py-3 px-4 text-red-400 hover:bg-red-500/10 flex justify-center items-center gap-2">
                                <i data-lucide="trash-2" class="w-4 h-4"></i> Excluir
                            </button>
                        </div>
                    `;
                    listaDiv.appendChild(favoritoElement);
                });
                lucide.createIcons();
            } else {
                listaDiv.classList.add('hidden');
                emptyState.classList.remove('hidden');
            }
        } catch (error) {
            console.error(error);
        }
    }

    window.editarFavorito = async function (id) {
        try {
            const response = await fetch(`../PHP/api_pontos_favoritos.php?id=${id}`);
            const result = await response.json();

            if (result.success) {
                const fav = result.data;
                document.getElementById('favorito-id').value = fav.ID_PONTO_INTERESSE;

                let nomeReal = fav.NOME || '';
                let iconName = 'map-pin';

                // Extrai o nome e ícone, removendo o separador
                if (nomeReal && nomeReal.includes(SEPARATOR)) {
                    const parts = nomeReal.split(SEPARATOR);
                    nomeReal = parts[0].trim();
                    iconName = parts[1] ? parts[1].trim() : 'map-pin';
                }

                document.getElementById('nome').value = nomeReal;
                window.selectIcon(iconName);

                document.getElementById('endereco-busca').value = fav.DESCRICAO || '';
                document.getElementById('input-cep').value = fav.CEP || '';
                document.getElementById('input-logradouro').value = fav.LOGRADOURO || '';
                document.getElementById('input-numero').value = fav.NUMERO_RESIDENCIA || '';
                document.getElementById('input-bairro').value = fav.bairro || '';
                document.getElementById('input-cidade').value = fav.cidade || '';
                document.getElementById('input-estado').value = fav.UF || '';

                const lat = parseFloat(fav.LATITUDE);
                const lng = parseFloat(fav.LONGITUDE);
                currentCoords = { lat, lng };

                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;

                window.abrirModal('modalCriarFavorito');

                if (!fav.CEP || fav.CEP === "") {
                    setTimeout(() => { handleManualLocationSelection(currentCoords); }, 500);
                }
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error(error);
            alert('Erro ao carregar dados.');
        }
    }

    window.excluirFavorito = async function (id) {
        if (!confirm('Deseja excluir este ponto?')) return;
        try {
            const response = await fetch(`../PHP/api_pontos_favoritos.php?id=${id}`, { method: 'DELETE' });
            const result = await response.json();
            if (result.success) carregarFavoritos();
            else alert(result.message);
        } catch (e) { alert('Erro ao excluir'); }
    }

    document.getElementById('form-favorito').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!document.getElementById('input-cep').value) {
            if (currentCoords) {
                handleManualLocationSelection(currentCoords);
                alert("Estamos atualizando o endereço deste ponto antigo. Por favor, clique em Salvar novamente em alguns segundos.");
                return;
            }
            alert("Endereço incompleto. Selecione um local válido no mapa/busca.");
            return;
        }

        const formData = new FormData(e.target);
        const id = document.getElementById('favorito-id').value;
        const dados = Object.fromEntries(formData.entries());

        const nomeDigitado = dados.nome;
        const iconeSelecionado = document.getElementById('input-icone-selecionado').value || 'map-pin';
        dados.nome = `${nomeDigitado}${SEPARATOR}${iconeSelecionado}`;

        const method = id ? 'PUT' : 'POST';
        const url = id ? `../PHP/api_pontos_favoritos.php?id=${id}` : '../PHP/api_pontos_favoritos.php';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            });
            const result = await response.json();

            if (result.success) {
                window.fecharModal('modalCriarFavorito');
                carregarFavoritos();
                const div = document.createElement('div');
                div.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg z-50 animate-slide-in';
                div.innerHTML = 'Salvo com sucesso!';
                document.body.appendChild(div);
                setTimeout(() => div.remove(), 3000);
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro na requisição.');
            console.error(error);
        }
    });

    carregarFavoritos();
});