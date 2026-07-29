class CargoVisualizer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.simulation = null;
        this.activeVehicleIndex = 0;
        this.mode = '3d';
        this.layerFilter = 'all';
        this.exploded = false;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.controls = null;
        this.currentMeshes = [];
        this.raycaster = null;
        this.pointer = null;
        this.animationFrame = null;
    }

    render(simulation) {
        this.simulation = simulation;
        if (!this.container) return;

        if (!simulation || !Array.isArray(simulation.veiculos) || !simulation.veiculos.length) {
            this.container.innerHTML = '<div class="text-center py-5 text-muted">Nenhum veículo alocado para visualizar.</div>';
            return;
        }

        if (this.activeVehicleIndex >= simulation.veiculos.length) {
            this.activeVehicleIndex = 0;
        }

        const vehicle = simulation.veiculos[this.activeVehicleIndex];
        const legend = [...new Map((vehicle.itens || []).map((item) => [item.base_nome, item.cor_hex])).entries()]
            .map(([base, color]) => `<span class="badge me-2" style="background:${color};color:#fff">${base}</span>`)
            .join('');

        this.container.innerHTML = `
            <div class="rounded-4 overflow-hidden border">
                <div class="bg-dark text-white p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-bold fs-5">${vehicle.slot_codigo}</div>
                            <small class="text-white-50">${vehicle.tipo_veiculo} | acesso ${vehicle.acesso_descarga} | ordem ${vehicle.ordem_descarga}</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            ${simulation.veiculos.map((v, index) => `<button class="btn btn-sm ${index === this.activeVehicleIndex ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setVehicle(${index})">${index + 1}</button>`).join('')}
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm ${this.mode === '3d' ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setMode('3d')">3D</button>
                            <button class="btn btn-sm ${this.mode === '2d' ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setMode('2d')">2D</button>
                            <button class="btn btn-sm ${this.layerFilter === 'all' ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setLayer('all')">Todos</button>
                            <button class="btn btn-sm ${this.layerFilter === '1' ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setLayer('1')">Lastro 1</button>
                            <button class="btn btn-sm ${this.layerFilter === '2' ? 'btn-primary' : 'btn-outline-light'}" onclick="window.resultVisualizer.setLayer('2')">Lastro 2</button>
                            <button class="btn btn-sm btn-outline-light" onclick="window.resultVisualizer.toggleExploded()">Explodir</button>
                        </div>
                        <div>${legend}</div>
                    </div>
                </div>
                <div class="p-3 bg-light">
                    <div id="cargoVisualizerStage" style="min-height:480px;"></div>
                </div>
            </div>
        `;

        if (this.mode === '2d' || !window.THREE) {
            this.render2D(vehicle);
        } else {
            this.render3D(vehicle);
        }
    }

    setVehicle(index) {
        this.activeVehicleIndex = index;
        this.render(this.simulation);
    }

    setMode(mode) {
        this.mode = mode;
        this.render(this.simulation);
    }

    setLayer(layer) {
        this.layerFilter = layer;
        this.render(this.simulation);
    }

    toggleExploded() {
        this.exploded = !this.exploded;
        this.render(this.simulation);
    }

    getFilteredItems(vehicle) {
        return (vehicle.itens || []).filter((item) => this.layerFilter === 'all' || String(item.lastro_posicao) === this.layerFilter);
    }

    render2D(vehicle) {
        const stage = document.getElementById('cargoVisualizerStage');
        const items = this.getFilteredItems(vehicle);
        const width = parseFloat(vehicle.largura_m || 2.45);
        const length = parseFloat(vehicle.comprimento_m || 8.5);
        const blocks = items.map((item) => {
            const left = (parseFloat(item.posicao_x) / length) * 100;
            const top = (parseFloat(item.posicao_y) / width) * 100;
            const blockWidth = Math.max(6, (parseFloat(item.comprimento_m) / length) * 100);
            const blockHeight = Math.max(10, (parseFloat(item.largura_m) / width) * 100);
            return `
                <div class="position-absolute rounded-3 border border-white text-white small fw-semibold p-1"
                     style="left:${left}%;top:${top}%;width:${blockWidth}%;height:${blockHeight}%;background:${item.cor_hex};"
                     title="${item.codigo_material} | ${item.base_nome} | ordem ${item.ordem_entrega}">
                    ${item.codigo_material}
                </div>
            `;
        }).join('');

        stage.innerHTML = `
            <div class="small text-muted mb-2">Vista superior da carroceria. Cabine à esquerda, traseira à direita.</div>
            <div class="position-relative rounded-4 border bg-white" style="height:480px;">
                <div class="position-absolute top-0 start-0 h-100 bg-dark text-white d-flex align-items-center justify-content-center" style="width:10%;">Cabine</div>
                <div class="position-absolute top-0 end-0 small text-muted p-2">Traseira</div>
                <div class="position-absolute" style="left:12%;top:5%;right:3%;bottom:5%;background:linear-gradient(180deg,#e2e8f0,#f8fafc);border:2px dashed #94a3b8;">
                    ${blocks}
                </div>
            </div>
        `;
    }

    render3D(vehicle) {
        const stage = document.getElementById('cargoVisualizerStage');
        if (!stage) return;

        if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
        stage.innerHTML = '<div id="cargo3dOverlay" class="small bg-dark text-white rounded-3 px-3 py-2 mb-2">Arraste para rotacionar e use o scroll para zoom.</div>';

        const wrapper = document.createElement('div');
        wrapper.style.height = '440px';
        wrapper.style.borderRadius = '18px';
        wrapper.style.overflow = 'hidden';
        stage.appendChild(wrapper);

        const length = parseFloat(vehicle.comprimento_m || 8.5);
        const width = parseFloat(vehicle.largura_m || 2.45);
        const height = parseFloat(vehicle.altura_m || 2.0);
        const items = this.getFilteredItems(vehicle);

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0f172a);
        this.camera = new THREE.PerspectiveCamera(45, wrapper.clientWidth / 440, 0.1, 1000);
        this.camera.position.set(length * 1.2, height * 2.6, width * 2.4);

        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(wrapper.clientWidth, 440);
        this.renderer.shadowMap.enabled = true;
        wrapper.appendChild(this.renderer.domElement);

        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.target.set(0, height / 2, 0);

        this.scene.add(new THREE.AmbientLight(0xffffff, 0.75));
        const light = new THREE.DirectionalLight(0xffffff, 0.8);
        light.position.set(20, 20, 10);
        light.castShadow = true;
        this.scene.add(light);
        this.scene.add(new THREE.GridHelper(length * 2.4, 40, 0x334155, 0x1e293b));

        const bedGroup = new THREE.Group();
        const bed = new THREE.Mesh(
            new THREE.BoxGeometry(length, 0.18, width),
            new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.7, metalness: 0.2 })
        );
        bed.position.set(0, 0.09, 0);
        bed.receiveShadow = true;
        bedGroup.add(bed);

        const walls = new THREE.LineSegments(
            new THREE.EdgesGeometry(new THREE.BoxGeometry(length, height, width)),
            new THREE.LineBasicMaterial({ color: 0x94a3b8 })
        );
        walls.position.set(0, height / 2, 0);
        bedGroup.add(walls);

        const cabin = new THREE.Mesh(
            new THREE.BoxGeometry(1.8, height + 0.6, width * 0.95),
            new THREE.MeshStandardMaterial({ color: 0x2563eb })
        );
        cabin.position.set(-(length / 2) - 0.9, (height + 0.6) / 2, 0);
        cabin.castShadow = true;
        bedGroup.add(cabin);
        this.scene.add(bedGroup);

        this.currentMeshes = [];
        items.forEach((item) => {
            const mesh = this.createMeshForItem(item);
            const x = -(length / 2) + parseFloat(item.posicao_x) + (parseFloat(item.comprimento_m) / 2);
            const z = -(width / 2) + parseFloat(item.posicao_y) + (parseFloat(item.largura_m) / 2);
            const y = parseFloat(item.posicao_z) + (parseFloat(item.altura_m) / 2) + 0.2 + (this.exploded && String(item.lastro_posicao) === '2' ? 1.2 : 0);
            mesh.position.set(x, y, z);
            mesh.userData = item;
            this.currentMeshes.push(mesh);
            this.scene.add(mesh);
        });

        this.raycaster = new THREE.Raycaster();
        this.pointer = new THREE.Vector2();
        this.renderer.domElement.addEventListener('pointermove', (event) => this.onPointerMove(event, wrapper));

        const animate = () => {
            this.animationFrame = requestAnimationFrame(animate);
            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        };
        animate();
    }

    createMeshForItem(item) {
        const color = new THREE.Color(item.cor_hex || '#2563eb');
        let mesh;
        if ((item.codigo_material || '').includes('BOB') || item.descricao_material.toLowerCase().includes('bobina')) {
            mesh = new THREE.Mesh(
                new THREE.CylinderGeometry(parseFloat(item.largura_m) / 2, parseFloat(item.largura_m) / 2, parseFloat(item.altura_m), 24),
                new THREE.MeshStandardMaterial({ color, roughness: 0.4, metalness: 0.5 })
            );
        } else {
            mesh = new THREE.Mesh(
                new THREE.BoxGeometry(parseFloat(item.comprimento_m), parseFloat(item.altura_m), parseFloat(item.largura_m)),
                new THREE.MeshStandardMaterial({ color, roughness: 0.5, metalness: 0.2 })
            );
        }
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        return mesh;
    }

    onPointerMove(event, wrapper) {
        if (!this.raycaster || !this.camera || !this.currentMeshes.length) return;
        const rect = wrapper.getBoundingClientRect();
        this.pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
        this.raycaster.setFromCamera(this.pointer, this.camera);
        const hit = this.raycaster.intersectObjects(this.currentMeshes, false)[0];
        const overlay = document.getElementById('cargo3dOverlay');
        if (!overlay) return;
        if (!hit) {
            overlay.textContent = 'Arraste para rotacionar e use o scroll para zoom.';
            return;
        }
        const item = hit.object.userData;
        overlay.innerHTML = `<strong>${item.codigo_material}</strong> | ${item.base_nome} | ordem ${item.ordem_entrega} | lastro ${item.lastro_posicao}`;
    }
}
