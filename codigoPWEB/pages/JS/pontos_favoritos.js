// pontos_favoritos.js

document.addEventListener('DOMContentLoaded', () => {
    let map = null;
    let marker = null;
    let geocoder = null;
    let autocomplete = null;
    let currentCoords = null;

    try {
        geocoder = new google.maps.Geocoder();
    } catch (e) {
        console.error("Erro ao inicializar Google Geocoder:", e);
    }

    // Função para extrair componentes de endereço do Google Places
    function extractAddressComponents(place) {
        let components = {
            cep: '',
            logradouro: '',
            numero: '',
            bairro: '',
            cidade: '',
            uf: ''
        };

        for (const component of place.address_components) {
            const types = component.types;
            if (types.includes('postal_code')) {
                components.cep = component.long_name;
            } else if (types.includes('route')) {
                components.logradouro = component.long_name;
            } else if (types.includes('street_number')) {
                components.numero = component.long_name;
            } else if (types.includes('sublocality_level_1') || types.includes('sublocality')) {
                components.bairro = component.long_name;
            } else if (types.includes('administrative_area_level_2')) {
                components.cidade = component.long_name;
            } else if (types.includes('administrative_area_level_1')) {
                components.uf = component.short_name;
            }
        }
        return components;
    }

    // Função para atualizar campos de endereço no formulário
    function updateAddressFields(components) {
        document.getElementById('input-cep').value = components.cep;
        document.getElementById('input-logradouro').value = components.logradouro;
        document.getElementById('input-numero').value = components.numero;
        document.getElementById('input-bairro').value = components.bairro;
        document.getElementById('input-cidade').value = components.cidade;
        document.getElementById('input-estado').value = components.uf;
    }

    // Limpa todos os campos de endereço
    function clearAddressFields() {
        updateAddressFields({cep: '', logradouro: '', numero: '', bairro: '', cidade: '', uf: ''});
        document.getElementById('endereco-busca').value = '';
    }

    window.initMap = function() {
        if (!map) {
            map = L.map('map-modal').setView([-22.3755861, -47.8825], 13);
            
            L.tileLayer('https://maps.geoapify.com/v1/tile/klokantech-basic/{z}/{x}/{y}.png?apiKey=a94d6d7cd9de4604aea43f8e8a1d0a36', {
                attribution: 'Powered by <a href="https://www.geoapify.com/" target="_blank">Geoapify</a>',
                maxZoom: 20
            }).addTo(map);

            // Clique no mapa para selecionar localização
            map.on('click', (e) => {
                const latlng = e.latlng;
                setMarker(latlng);
                
                updateAddressFields({cep: '', logradouro: '', numero: '', bairro: '', cidade: '', uf: ''});
                
                geocoder.geocode({ 'location': latlng }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        document.getElementById('endereco-busca').value = results[0].formatted_address;
                    }
                });
            });

            // Inicializar Autocomplete
            const input = document.getElementById('endereco-busca');
            const options = {
                componentRestrictions: { country: 'br' },
                fields: ['formatted_address', 'geometry', 'name', 'address_components'], 
                strictBounds: false,
            };

            autocomplete = new google.maps.places.Autocomplete(input, options);

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place.geometry) {
                    clearAddressFields();
                    return;
                }

                const latlng = { 
                    lat: place.geometry.location.lat(), 
                    lng: place.geometry.location.lng() 
                };

                map.setView(latlng, 16); 
                setMarker(latlng);
                
                const addressComponents = extractAddressComponents(place);
                updateAddressFields(addressComponents);

                document.getElementById('endereco-busca').value = place.formatted_address;
            });
        }
    }

    function setMarker(latlng) {
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker(latlng, { draggable: true }).addTo(map);
        currentCoords = latlng;

        document.getElementById('latitude').value = latlng.lat;
        document.getElementById('longitude').value = latlng.lng;
        document.getElementById('coordenadas-info').textContent = `📍 Coordenadas: Lat ${latlng.lat.toFixed(6)}, Lng ${latlng.lng.toFixed(6)}`;
        document.getElementById('coordenadas-info').classList.remove('hidden');

        marker.on('dragend', (e) => {
            const newLatlng = marker.getLatLng();
            currentCoords = newLatlng;
            document.getElementById('latitude').value = newLatlng.lat;
            document.getElementById('longitude').value = newLatlng.lng;
            document.getElementById('coordenadas-info').textContent = `📍 Coordenadas: Lat ${newLatlng.lat.toFixed(6)}, Lng ${newLatlng.lng.toFixed(6)}`;
            
            updateAddressFields({cep: '', logradouro: '', numero: '', bairro: '', cidade: '', uf: ''});
        });
    }

    async function carregarFavoritos() {
        const listaDiv = document.getElementById('favorites-list') || document.getElementById('lista-favoritos');
        const emptyState = document.getElementById('empty-state');
        
        listaDiv.innerHTML = '<div class="col-span-full bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 p-6 flex items-center justify-center min-h-[200px]"><p class="text-gray-400">Carregando pontos favoritos...</p></div>';

        try {
            const response = await fetch('../PHP/api_pontos_favoritos.php');
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                listaDiv.innerHTML = '';
                if (emptyState) emptyState.classList.add('hidden');
                
                // CORREÇÃO 1: Remove a classe 'hidden' para exibir a lista.
                listaDiv.classList.remove('hidden'); 
                
                result.data.forEach(fav => {
                    const favoritoElement = document.createElement('div');
                    favoritoElement.className = 'favorite-card bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-xl rounded-2xl border border-cyan-500/20 overflow-hidden'; 
                    
                    let enderecoCompleto = fav.LOGRADOURO || 'Endereço não especificado';
                    if (fav.NUMERO_RESIDENCIA) enderecoCompleto += `, ${fav.NUMERO_RESIDENCIA}`;
                    if (fav.bairro) enderecoCompleto += ` - ${fav.bairro}`;
                    if (fav.cidade) enderecoCompleto += `, ${fav.cidade}`;
                    if (fav.UF) enderecoCompleto += `/${fav.UF}`;

                    favoritoElement.innerHTML = `
                        <div class="p-6">
                            <div class="flex items-start gap-4 mb-4">
                                <div class="w-14 h-14 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg shadow-cyan-500/30">
                                    <i data-lucide="heart" class="w-7 h-7 text-white"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-xl font-bold text-white mb-1 truncate">${fav.NOME}</h3>
                                    <p class="text-gray-400 text-sm leading-relaxed">${enderecoCompleto}</p>
                                </div>
                            </div>
                            
                            ${fav.DESCRICAO ? `
                            <div class="mb-4 p-3 bg-slate-900/50 rounded-lg border border-cyan-500/10">
                                <p class="text-gray-300 text-sm">${fav.DESCRICAO}</p>
                            </div>
                            ` : ''}
                            
                            <div class="flex items-center gap-2 text-xs text-gray-500 mb-4 p-2 bg-slate-900/30 rounded-lg">
                                <i data-lucide="navigation" class="w-4 h-4 text-cyan-400"></i>
                                <span class="font-mono">${parseFloat(fav.LATITUDE).toFixed(6)}, ${parseFloat(fav.LONGITUDE).toFixed(6)}</span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 border-t border-cyan-500/20">
                            <button onclick="importarParaSimulacao(${fav.ID_PONTO_INTERESSE}, '${fav.NOME.replace(/'/g, "\\'")}', ${fav.LATITUDE}, ${fav.LONGITUDE})" 
                                    class="flex items-center justify-center gap-2 py-3 px-4 text-green-400 hover:bg-green-500/10 transition-all duration-300 border-r border-cyan-500/20 group"
                                    title="Importar para Simulação">
                                <i data-lucide="route" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                <span class="text-sm font-semibold hidden sm:inline">Usar Rota</span>
                            </button>
                            <button onclick="editarFavorito(${fav.ID_PONTO_INTERESSE})" 
                                    class="flex items-center justify-center gap-2 py-3 px-4 text-cyan-400 hover:bg-cyan-500/10 transition-all duration-300 border-r border-cyan-500/20 group"
                                    title="Editar">
                                <i data-lucide="pencil" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                <span class="text-sm font-semibold hidden sm:inline">Editar</span>
                            </button>
                            <button onclick="excluirFavorito(${fav.ID_PONTO_INTERESSE})" 
                                    class="flex items-center justify-center gap-2 py-3 px-4 text-red-400 hover:bg-red-500/10 transition-all duration-300 group"
                                    title="Excluir">
                                <i data-lucide="trash-2" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                <span class="text-sm font-semibold hidden sm:inline">Excluir</span>
                            </button>
                        </div>
                    `;
                    listaDiv.appendChild(favoritoElement);
                });
                lucide.createIcons();
            } else if (result.success) {
                listaDiv.innerHTML = '';
                // CORREÇÃO 2: Adiciona a classe 'hidden' à lista e exibe o estado vazio.
                listaDiv.classList.add('hidden');
                if (emptyState) emptyState.classList.remove('hidden');
                lucide.createIcons();
            } else {
                listaDiv.innerHTML = `<div class="col-span-full bg-red-500/20 border border-red-500/30 rounded-2xl p-6"><p class="text-red-400">Erro ao carregar favoritos: ${result.message}</p></div>`;
                // Se houver erro, exibe a div da lista para mostrar a mensagem de erro
                listaDiv.classList.remove('hidden'); 
            }
        } catch (error) {
            listaDiv.innerHTML = '<div class="col-span-full bg-red-500/20 border border-red-500/30 rounded-2xl p-6"><p class="text-red-400">Erro de conexão ao carregar favoritos.</p></div>';
            // Se houver erro, exibe a div da lista para mostrar a mensagem de erro
            listaDiv.classList.remove('hidden'); 
            console.error('Erro ao carregar favoritos:', error);
        }
    }
    
    window.importarParaSimulacao = function(id, nome, lat, lng) {
        // Criar notificação de sucesso
        const notification = document.createElement('div');
        notification.className = 'fixed bottom-6 right-6 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-50 animate-slide-in';
        notification.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5"></i><span class="font-semibold">Local "${nome}" importado para simulação!</span>`;
        document.body.appendChild(notification);
        lucide.createIcons();
        
        setTimeout(() => notification.remove(), 3000);
        
        // Aqui você pode adicionar a lógica para realmente importar para simulação
        // Por exemplo: redirecionar para página de simulação com os parâmetros
        console.log(`Importando: ${nome} (${lat}, ${lng})`);
        window.location.href = `simulador.php?origem_lat=${lat}&origem_lng=${lng}&origem_nome=${encodeURIComponent(nome)}`;
    }
    
    window.editarFavorito = async function(id) {
        try {
            const response = await fetch(`../PHP/api_pontos_favoritos.php?id=${id}`);
            const result = await response.json();

            if (result.success) {
                const fav = result.data;
                
                document.getElementById('favorito-id').value = fav.ID_PONTO_INTERESSE;
                document.getElementById('nome').value = fav.NOME;
                document.getElementById('endereco-busca').value = fav.DESCRICAO || '';
                
                document.getElementById('input-cep').value = fav.CEP || '';
                document.getElementById('input-logradouro').value = fav.LOGRADOURO || '';
                document.getElementById('input-numero').value = fav.NUMERO_RESIDENCIA || '';
                
                document.getElementById('input-bairro').value = fav.bairro || '';
                document.getElementById('input-cidade').value = fav.cidade || '';
                document.getElementById('input-estado').value = fav.UF || '';

                const modal = document.getElementById('modalCriarFavorito');
                modal.classList.add('active'); 

                const latlng = { lat: parseFloat(fav.LATITUDE), lng: parseFloat(fav.LONGITUDE) };
                
                setTimeout(() => {
                    if (window.initMap) window.initMap();
                    if (map) {
                        map.setView(latlng, 16); 
                        setMarker(latlng);
                    } else {
                        document.getElementById('latitude').value = latlng.lat;
                        document.getElementById('longitude').value = latlng.lng;
                        currentCoords = latlng;
                    }
                    lucide.createIcons();
                }, 100);
            } else {
                alert('Erro ao carregar favorito para edição: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao carregar favorito.');
            console.error(error);
        }
    }

    window.excluirFavorito = async function(id) {
        if (!confirm('Tem certeza que deseja excluir este ponto favorito?')) {
            return;
        }

        try {
            const response = await fetch(`../PHP/api_pontos_favoritos.php?id=${id}`, {
                method: 'DELETE'
            });

            const result = await response.json();

            if (result.success) {
                // Criar notificação de sucesso
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-6 right-6 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-50 animate-slide-in';
                notification.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i><span class="font-semibold">Ponto favorito excluído com sucesso!</span>';
                document.body.appendChild(notification);
                lucide.createIcons();
                
                setTimeout(() => notification.remove(), 3000);
                carregarFavoritos();
            } else {
                alert('Erro ao excluir: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao excluir favorito.');
            console.error(error);
        }
    }

    // Lógica para salvar o formulário
    document.getElementById('form-favorito').addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!currentCoords) {
            alert('Por favor, selecione uma localização no mapa ou busque um endereço.');
            return;
        }
        
        const cepValue = document.getElementById('input-cep').value;
        if (!cepValue || cepValue.length < 8) {
            alert('O CEP é obrigatório e precisa ter 8 dígitos (apenas números). Preencha ou corrija.');
            return;
        }

        const formData = new FormData(e.target);
        const id = document.getElementById('favorito-id').value;
        
        const dados = {
            nome: formData.get('nome'),
            descricao: formData.get('descricao'),
            latitude: formData.get('latitude'),
            longitude: formData.get('longitude'),
            cep: formData.get('cep'),
            logradouro: formData.get('logradouro'),
            numero_residencia: formData.get('numero_residencia'), 
            bairro_nome: formData.get('bairro_nome'),
            cidade_nome: formData.get('cidade_nome'),
            estado_uf: formData.get('estado_uf')
        };
        
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
                
                // Criar notificação de sucesso
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-6 right-6 bg-gradient-to-r from-cyan-500 to-blue-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-50 animate-slide-in';
                notification.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5"></i><span class="font-semibold">Ponto ${id ? 'atualizado' : 'cadastrado'} com sucesso!</span>`;
                document.body.appendChild(notification);
                lucide.createIcons();
                
                setTimeout(() => notification.remove(), 3000);
                
                carregarFavoritos();
                
                document.getElementById('form-favorito').reset();
                document.getElementById('favorito-id').value = '';
                document.getElementById('coordenadas-info').classList.add('hidden');
                clearAddressFields(); 

                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                    currentCoords = null;
                }
            } else {
                // Criar notificação de erro estilizada
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-6 right-6 bg-gradient-to-r from-red-500 to-rose-500 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-50 animate-slide-in max-w-md';
                notification.innerHTML = `<i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i><span class="font-semibold">${result.message}</span>`;
                document.body.appendChild(notification);
                lucide.createIcons();
                
                setTimeout(() => notification.remove(), 5000);
            }
        } catch (error) {
            alert('Erro ao salvar favorito.');
            console.error(error);
        }
    });

    window.abrirModal = function(id) {
        document.getElementById(id).classList.add('active');
        setTimeout(() => {
            lucide.createIcons();
            if (window.initMap) window.initMap();
        }, 100);
    }
    
    window.fecharModal = function(id) {
        document.getElementById(id).classList.remove('active');
    }

    carregarFavoritos();
});