// Interactive 3D & 2D Cargo Visualizer Engine (Three.js + OrbitControls)

class CargoVisualizer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.simData = null;
        this.viewMode = '3d'; // '3d' or '2d'
        this.currentLayer = 1; // 1 = Lastro 1, 2 = Lastro 2
        this.isExploded = false;
        this.autoRotate = false;
        
        // Three.js instances
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.raycaster = null;
        this.mouse = null;
        this.cargoMeshes = [];
        this.lastroGroups = { 1: null, 2: null };
        this.animationFrameId = null;
    }

    render(data) {
        this.simData = data;
        if (!this.container) return;

        if (this.viewMode === '3d' && window.THREE) {
            this.render3D(data);
        } else {
            this.render2D(data);
        }
    }

    switchViewMode(mode) {
        this.viewMode = mode;
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        if (this.simData) {
            this.render(this.simData);
        }
    }

    // -------------------------------------------------------------
    // RENDERIZAÇÃO 3D INTERATIVA (Three.js)
    // -------------------------------------------------------------
    render3D(data) {
        const veiculoComp = parseFloat(data.veiculo_comprimento_m || data.comprimento_m || 8.5);
        const veiculoLarg = parseFloat(data.veiculo_largura_m || data.largura_m || 2.45);
        const veiculoAlt = parseFloat(data.veiculo_altura_m || data.altura_m || 2.0);
        const veiculoTipo = (data.veiculo_tipo || data.tipo || 'Truck').toLowerCase();

        this.container.innerHTML = `
            <div class="cargo-simulation-container p-3 bg-dark rounded-4 shadow-sm text-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0 text-white fw-bold">
                            <i class="fa-solid fa-cube text-primary me-2"></i>Visualização 3D Interativa do Veículo
                        </h4>
                        <small class="text-white-50">
                            Veículo: <strong>${data.veiculo_nome || data.nome}</strong> (${veiculoComp}m x ${veiculoLarg}m x ${veiculoAlt}m)
                        </small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-sm btn-outline-light ${this.viewMode === '3d' ? 'active' : ''}" onclick="window.cargoVis.switchViewMode('3d')">
                            <i class="fa-solid fa-cubes me-1"></i> Modo 3D
                        </button>
                        <button class="btn btn-sm btn-outline-light ${this.viewMode === '2d' ? 'active' : ''}" onclick="window.cargoVis.switchViewMode('2d')">
                            <i class="fa-solid fa-table-cells me-1"></i> Modo 2D
                        </button>
                    </div>
                </div>

                <div class="canvas-3d-wrapper" id="canvas3dContainer">
                    <div class="canvas-3d-controls">
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.setCameraView('iso')" title="Perspectiva Isométrica">
                            <i class="fa-solid fa-camera me-1"></i> 3D
                        </button>
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.setCameraView('top')" title="Visão Superior">
                            <i class="fa-solid fa-arrow-down me-1"></i> Topo
                        </button>
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.setCameraView('side')" title="Visão Lateral">
                            <i class="fa-solid fa-arrow-right me-1"></i> Lateral
                        </button>
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.setCameraView('front')" title="Visão Frontal Cabine">
                            <i class="fa-solid fa-truck-front me-1"></i> Cabine
                        </button>
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.toggleAutoRotate()" title="Girar 360 Graus">
                            <i class="fa-solid fa-rotate me-1"></i> Giro 360°
                        </button>
                        <button class="btn btn-xs btn-sm btn-dark text-white border-secondary" onclick="window.cargoVis.toggleExplodeLayers()" title="Explodir Lastros">
                            <i class="fa-solid fa-layer-group me-1"></i> Explodir Lastros
                        </button>
                    </div>

                    <div class="canvas-3d-info" id="cargo3dOverlay">
                        <i class="fa-solid fa-hand-pointer me-1 text-primary"></i>
                        <span>Clique e arraste com o mouse para <strong>rotacionar 360°</strong>. Role o scroll para dar zoom.</span>
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center text-white-50 small">
                    <div>
                        <span class="badge bg-warning text-dark me-2"><i class="fa-solid fa-circle me-1"></i> Bobina</span>
                        <span class="badge bg-danger me-2"><i class="fa-solid fa-bolt me-1"></i> Transformador</span>
                        <span class="badge bg-secondary me-2"><i class="fa-solid fa-grip-lines me-1"></i> Poste Concreto</span>
                        <span class="badge bg-success me-2"><i class="fa-solid fa-box me-1"></i> Caixas/Paletes</span>
                    </div>
                    <div>
                        Centro de Gravidade Longitudinal: <strong class="text-info">${data.centro_gravidade_x || 50}%</strong>
                    </div>
                </div>
            </div>
        `;

        const wrapper = document.getElementById('canvas3dContainer');
        const width = wrapper.clientWidth;
        const height = wrapper.clientHeight || 480;

        // Scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0f172a);
        this.scene.fog = new THREE.FogExp2(0x0f172a, 0.025);

        // Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        this.camera.position.set(veiculoComp * 1.3, veiculoAlt * 3, veiculoLarg * 2.5);

        // Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        // Clear previous canvas if any
        wrapper.appendChild(this.renderer.domElement);

        // Orbit Controls for Manual 360 Rotation & Zooming
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.target.set(0, veiculoAlt / 2, 0);
        this.controls.maxPolarAngle = Math.PI / 2 + 0.05; // Não afundar no chão
        this.controls.minDistance = 3;
        this.controls.maxDistance = 60;

        // Lights
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.65);
        this.scene.add(ambientLight);

        const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
        dirLight.position.set(20, 30, 15);
        dirLight.castShadow = true;
        dirLight.shadow.mapSize.width = 2048;
        dirLight.shadow.mapSize.height = 2048;
        this.scene.add(dirLight);

        const fillLight = new THREE.DirectionalLight(0x38bdf8, 0.3);
        fillLight.position.set(-20, 15, -15);
        this.scene.add(fillLight);

        // Ground Grid
        const gridHelper = new THREE.GridHelper(veiculoComp * 3, 30, 0x334155, 0x1e293b);
        gridHelper.position.y = -0.01;
        this.scene.add(gridHelper);

        // Build 3D Truck Model
        this.build3DTruck(veiculoComp, veiculoLarg, veiculoAlt, veiculoTipo);

        // Build 3D Cargo Items
        this.build3DCargo(data.itens || [], veiculoComp, veiculoLarg);

        // Raycasting for Interaction
        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();

        this.renderer.domElement.addEventListener('pointermove', (e) => this.onPointerMove(e, wrapper));
        this.renderer.domElement.addEventListener('click', (e) => this.onPointerClick(e, wrapper));

        // Animation Loop
        const animate = () => {
            this.animationFrameId = requestAnimationFrame(animate);
            if (this.controls) {
                if (this.autoRotate) {
                    this.controls.autoRotate = true;
                    this.controls.autoRotateSpeed = 2.0;
                } else {
                    this.controls.autoRotate = false;
                }
                this.controls.update();
            }
            if (this.renderer && this.scene && this.camera) {
                this.renderer.render(this.scene, this.camera);
            }
        };
        animate();
    }

    // -------------------------------------------------------------
    // CONSTRUÇÃO DO VEÍCULO EM 3D PROCEDURAL
    // -------------------------------------------------------------
    build3DTruck(comp, larg, alt, tipo) {
        const truckGroup = new THREE.Group();

        // 1. Plataforma / Carroceria (Bed)
        const bedThickness = 0.2;
        const bedGeo = new THREE.BoxGeometry(comp, bedThickness, larg);
        const bedMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6, metalness: 0.4 });
        const bedMesh = new THREE.Mesh(bedGeo, bedMat);
        bedMesh.position.set(0, bedThickness / 2, 0);
        bedMesh.receiveShadow = true;
        bedMesh.castShadow = true;
        truckGroup.add(bedMesh);

        // Piso Madeira Interno
        const floorGeo = new THREE.PlaneGeometry(comp * 0.98, larg * 0.98);
        const floorMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.8 });
        const floorMesh = new THREE.Mesh(floorGeo, floorMat);
        floorMesh.rotation.x = -Math.PI / 2;
        floorMesh.position.set(0, bedThickness + 0.005, 0);
        truckGroup.add(floorMesh);

        // 2. Cabine do Veículo (Frente X = -comp / 2)
        const cabinComp = 1.8;
        const cabinAlt = alt + 0.6;
        const cabinLarg = larg * 0.95;
        const cabinGeo = new THREE.BoxGeometry(cabinComp, cabinAlt, cabinLarg);

        let cabinColor = 0x2563eb; // Truck = Azul
        if (tipo === 'munck') cabinColor = 0xd97706; // Munck = Laranja
        if (tipo === 'carreta') cabinColor = 0x059669; // Carreta = Verde

        const cabinMat = new THREE.MeshStandardMaterial({ color: cabinColor, roughness: 0.3, metalness: 0.5 });
        const cabinMesh = new THREE.Mesh(cabinGeo, cabinMat);
        cabinMesh.position.set(-comp / 2 - cabinComp / 2, cabinAlt / 2, 0);
        cabinMesh.castShadow = true;
        truckGroup.add(cabinMesh);

        // Para-brisa (Windshield)
        const glassGeo = new THREE.BoxGeometry(0.05, cabinAlt * 0.4, cabinLarg * 0.85);
        const glassMat = new THREE.MeshStandardMaterial({ color: 0x38bdf8, roughness: 0.1, metalness: 0.9, transparent: true, opacity: 0.7 });
        const glassMesh = new THREE.Mesh(glassGeo, glassMat);
        glassMesh.position.set(-comp / 2 - 0.05, cabinAlt * 0.65, 0);
        truckGroup.add(glassMesh);

        // 3. Rodas (Wheels)
        const wheelRadius = 0.5;
        const wheelTube = 0.35;
        const wheelGeo = new THREE.CylinderGeometry(wheelRadius, wheelRadius, wheelTube, 24);
        const wheelMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.9 });
        const rimGeo = new THREE.CylinderGeometry(wheelRadius * 0.55, wheelRadius * 0.55, wheelTube + 0.02, 16);
        const rimMat = new THREE.MeshStandardMaterial({ color: 0x94a3b8, metalness: 0.8, roughness: 0.2 });

        const wheelPositions = [
            [-comp / 2 - cabinComp * 0.4, -wheelRadius * 0.5, larg / 2 + 0.1],
            [-comp / 2 - cabinComp * 0.4, -wheelRadius * 0.5, -larg / 2 - 0.1],
            [comp * 0.2, -wheelRadius * 0.5, larg / 2 + 0.1],
            [comp * 0.2, -wheelRadius * 0.5, -larg / 2 - 0.1],
            [comp * 0.4, -wheelRadius * 0.5, larg / 2 + 0.1],
            [comp * 0.4, -wheelRadius * 0.5, -larg / 2 - 0.1],
        ];

        wheelPositions.forEach(pos => {
            const wMesh = new THREE.Mesh(wheelGeo, wheelMat);
            wMesh.rotation.x = Math.PI / 2;
            wMesh.position.set(pos[0], pos[1], pos[2]);
            const rMesh = new THREE.Mesh(rimGeo, rimMat);
            rMesh.rotation.x = Math.PI / 2;
            rMesh.position.set(pos[0], pos[1], pos[2]);
            truckGroup.add(wMesh);
            truckGroup.add(rMesh);
        });

        // 4. Guindaste Munck (Caso seja tipo Munck)
        if (tipo === 'munck') {
            const craneBaseGeo = new THREE.CylinderGeometry(0.3, 0.4, 1.2, 16);
            const craneMat = new THREE.MeshStandardMaterial({ color: 0xd97706, metalness: 0.6, roughness: 0.4 });
            const craneBase = new THREE.Mesh(craneBaseGeo, craneMat);
            craneBase.position.set(-comp / 2 + 0.4, bedThickness + 0.6, 0);
            truckGroup.add(craneBase);

            const armGeo = new THREE.BoxGeometry(2.0, 0.25, 0.25);
            const armMesh = new THREE.Mesh(armGeo, craneMat);
            armMesh.position.set(-comp / 2 + 1.2, bedThickness + 1.3, 0);
            armMesh.rotation.z = -0.2;
            truckGroup.add(armMesh);
        }

        this.scene.add(truckGroup);
    }

    // -------------------------------------------------------------
    // CONSTRUÇÃO DA CARGA 3D PROCEDURAL
    // -------------------------------------------------------------
    build3DCargo(itens, veiculoComp, veiculoLarg) {
        this.cargoMeshes = [];
        this.lastroGroups = {
            1: new THREE.Group(),
            2: new THREE.Group()
        };

        this.scene.add(this.lastroGroups[1]);
        this.scene.add(this.lastroGroups[2]);

        const offsetOriginX = -veiculoComp / 2;
        const offsetOriginZ = -veiculoLarg / 2;

        itens.forEach((it, idx) => {
            if (it.status_alocacao !== 'alocado') return;

            const posX = parseFloat(it.posicao_x || 0);
            const posY = parseFloat(it.posicao_y || 0);
            const posZ = parseFloat(it.posicao_z || 0);
            const comp = Math.max(0.4, parseFloat(it.comprimento_m || 1.2));
            const larg = Math.max(0.4, parseFloat(it.largura_m || 1.0));
            const alt = Math.max(0.4, parseFloat(it.altura_m || 0.8));
            const lastro = parseInt(it.lastro_posicao || 1);
            const cod = (it.codigo_material || '').toUpperCase();

            let mesh;
            let mat;

            if (cod.includes('BOB')) {
                // Bobina (Cilindro 3D)
                const radius = Math.min(comp, larg) / 2;
                const bobGeo = new THREE.CylinderGeometry(radius, radius, alt, 24);
                mat = new THREE.MeshStandardMaterial({
                    color: 0xf59e0b,
                    roughness: 0.4,
                    metalness: 0.6
                });
                mesh = new THREE.Mesh(bobGeo, mat);
                // Centralizar cilindro
                mesh.position.set(
                    offsetOriginX + posX + comp / 2,
                    posZ + alt / 2 + 0.2,
                    offsetOriginZ + posY + larg / 2
                );
            } else if (cod.includes('TRF')) {
                // Transformador 3D com buchas superiores
                const group = new THREE.Group();
                const bodyGeo = new THREE.BoxGeometry(comp, alt * 0.8, larg);
                mat = new THREE.MeshStandardMaterial({ color: 0xef4444, roughness: 0.5, metalness: 0.3 });
                const bodyMesh = new THREE.Mesh(bodyGeo, mat);
                bodyMesh.castShadow = true;
                group.add(bodyMesh);

                // Buchas de porcelana no topo
                const bushGeo = new THREE.CylinderGeometry(0.08, 0.08, alt * 0.3, 12);
                const bushMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.2 });
                [-comp * 0.25, 0, comp * 0.25].forEach(bx => {
                    const bMesh = new THREE.Mesh(bushGeo, bushMat);
                    bMesh.position.set(bx, alt * 0.5, 0);
                    group.add(bMesh);
                });

                group.position.set(
                    offsetOriginX + posX + comp / 2,
                    posZ + alt / 2 + 0.2,
                    offsetOriginZ + posY + larg / 2
                );
                mesh = group;
            } else if (cod.includes('POS')) {
                // Poste de Concreto Longo 3D
                const postGeo = new THREE.BoxGeometry(comp, alt, larg);
                mat = new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: 0.9 });
                mesh = new THREE.Mesh(postGeo, mat);
                mesh.position.set(
                    offsetOriginX + posX + comp / 2,
                    posZ + alt / 2 + 0.2,
                    offsetOriginZ + posY + larg / 2
                );
            } else {
                // Caixas / Paletes 3D
                const boxGeo = new THREE.BoxGeometry(comp, alt, larg);
                mat = new THREE.MeshStandardMaterial({ color: 0x10b981, roughness: 0.6, metalness: 0.2 });
                mesh = new THREE.Mesh(boxGeo, mat);
                mesh.position.set(
                    offsetOriginX + posX + comp / 2,
                    posZ + alt / 2 + 0.2,
                    offsetOriginZ + posY + larg / 2
                );
            }

            if (mesh.isMesh) {
                mesh.castShadow = true;
                mesh.receiveShadow = true;
            } else {
                mesh.traverse(child => {
                    if (child.isMesh) {
                        child.castShadow = true;
                        child.receiveShadow = true;
                    }
                });
            }

            mesh.userData = { ...it, materialData: it };
            this.cargoMeshes.push(mesh);

            if (lastro === 2) {
                this.lastroGroups[2].add(mesh);
            } else {
                this.lastroGroups[1].add(mesh);
            }
        });
    }

    // -------------------------------------------------------------
    // CONTROLES DE CÂMERA E INTERAÇÃO 3D
    // -------------------------------------------------------------
    setCameraView(view) {
        if (!this.controls || !this.simData) return;

        const comp = parseFloat(this.simData.veiculo_comprimento_m || this.simData.comprimento_m || 8.5);
        const larg = parseFloat(this.simData.veiculo_largura_m || this.simData.largura_m || 2.45);
        const alt = parseFloat(this.simData.veiculo_altura_m || this.simData.altura_m || 2.0);

        this.controls.target.set(0, alt / 2, 0);

        switch (view) {
            case 'top':
                this.camera.position.set(0, comp * 1.8, 0.001);
                break;
            case 'side':
                this.camera.position.set(0, alt, larg * 3);
                break;
            case 'front':
                this.camera.position.set(-comp * 2, alt * 1.2, 0);
                break;
            case 'iso':
            default:
                this.camera.position.set(comp * 1.3, alt * 3, larg * 2.5);
                break;
        }
        this.controls.update();
    }

    toggleAutoRotate() {
        this.autoRotate = !this.autoRotate;
    }

    toggleExplodeLayers() {
        this.isExploded = !this.isExploded;
        if (this.lastroGroups[2]) {
            const targetY = this.isExploded ? 2.2 : 0;
            this.lastroGroups[2].position.y = targetY;
        }
    }

    onPointerMove(e, container) {
        const rect = container.getBoundingClientRect();
        this.mouse.x = ((e.clientX - rect.left) / container.clientWidth) * 2 - 1;
        this.mouse.y = -((e.clientY - rect.top) / container.clientHeight) * 2 + 1;

        if (this.raycaster && this.scene && this.camera) {
            this.raycaster.setFromCamera(this.mouse, this.camera);
            const intersects = this.raycaster.intersectObjects(this.cargoMeshes, true);

            const overlay = document.getElementById('cargo3dOverlay');
            if (intersects.length > 0) {
                let target = intersects[0].object;
                while (target.parent && !target.userData.codigo_material) {
                    target = target.parent;
                }
                const data = target.userData;
                if (data && data.codigo_material && overlay) {
                    overlay.innerHTML = `
                        <strong class="text-warning">${data.codigo_material}</strong>: ${data.descricao_material}<br>
                        <span>Peso: <strong>${data.peso_unitario_kg} kg</strong> | Lastro: <strong>${data.lastro_posicao}</strong></span>
                    `;
                }
            }
        }
    }

    onPointerClick(e, container) {
        this.onPointerMove(e, container);
    }

    // -------------------------------------------------------------
    // RENDERIZAÇÃO 2D ESQUEMÁTICA (Fallback)
    // -------------------------------------------------------------
    render2D(data) {
        const veiculo = data;
        const itens = data.itens || [];
        const veiculoComp = parseFloat(veiculo.veiculo_comprimento_m || veiculo.comprimento_m || 8.5);
        const veiculoLarg = parseFloat(veiculo.veiculo_largura_m || veiculo.largura_m || 2.45);

        const itensLastro = itens.filter(it => parseInt(it.lastro_posicao) === this.currentLayer && it.status_alocacao === 'alocado');

        let html = `
            <div class="cargo-simulation-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0 text-white"><i class="fa-solid fa-cube text-primary me-2"></i>Visão Superior da Carroceria (2D)</h4>
                        <small class="text-white-50">Veículo: ${veiculo.veiculo_nome || veiculo.nome} (${veiculoComp}m x ${veiculoLarg}m)</small>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm ${this.currentLayer === 1 ? 'btn-primary' : 'btn-outline-light'}" onclick="window.cargoVis.switchLayer(1)">
                                <i class="fa-solid fa-layer-group me-1"></i> Lastro 1 (Piso)
                            </button>
                            <button class="btn btn-sm ${this.currentLayer === 2 ? 'btn-primary' : 'btn-outline-light'}" onclick="window.cargoVis.switchLayer(2)">
                                <i class="fa-solid fa-layer-group me-1"></i> Lastro 2 (Superior)
                            </button>
                        </div>
                        <button class="btn btn-sm btn-outline-light" onclick="window.cargoVis.switchViewMode('3d')">
                            <i class="fa-solid fa-cubes me-1"></i> Alternar para 3D Interativo
                        </button>
                    </div>
                </div>

                <div class="truck-bed-visual">
                    <div class="truck-bed-header">
                        <span>CABINE (FRENTE DO VEÍCULO)</span>
                        <span>TRASEIRA DA CARROCERIA</span>
                    </div>
                    <div class="truck-cabin">FRENTE</div>
                    <div class="truck-load-area" id="loadArea">
        `;

        itensLastro.forEach((it) => {
            const posX = parseFloat(it.posicao_x || 0);
            const posY = parseFloat(it.posicao_y || 0);
            const comp = parseFloat(it.comprimento_m || 1.2);
            const larg = parseFloat(it.largura_m || 1.0);

            const leftPct = (posX / veiculoComp) * 100;
            const topPct = (posY / veiculoLarg) * 100;
            const widthPct = Math.max(8, (comp / veiculoComp) * 100);
            const heightPct = Math.max(12, (larg / veiculoLarg) * 100);

            let blockClass = 'cargo-block-outro';
            if (it.codigo_material && it.codigo_material.includes('BOB')) blockClass = 'cargo-block-bobina';
            else if (it.codigo_material && it.codigo_material.includes('TRF')) blockClass = 'cargo-block-transformador';
            else if (it.codigo_material && it.codigo_material.includes('POS')) blockClass = 'cargo-block-poste';
            else if (it.codigo_material && it.codigo_material.includes('CX')) blockClass = 'cargo-block-caixa';

            html += `
                <div class="cargo-block ${blockClass}" 
                     style="left: ${leftPct}%; top: ${topPct}%; width: ${widthPct}%; height: ${heightPct}%;"
                     data-bs-toggle="tooltip" 
                     title="${it.codigo_material} - ${it.descricao_material} (${it.peso_unitario_kg}kg)">
                    ${it.codigo_material}
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
        
        const tooltipTriggerList = [].slice.call(this.container.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    switchLayer(layer) {
        this.currentLayer = layer;
        if (this.simData) {
            this.render(this.simData);
        }
    }
}

// Global instance helper
document.addEventListener('DOMContentLoaded', () => {
    window.cargoVis = new CargoVisualizer('visualizadorCarga');
});
