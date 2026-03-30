document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicialización de Fabric.js Canvas
    const canvasEl = document.getElementById('cameraCanvas');
    const wrapper = document.querySelector('.canvas-wrapper');

    const CANVAS_WIDTH = 800;
    const CANVAS_HEIGHT = 600;

    const canvas = new fabric.Canvas('cameraCanvas', {
        width: CANVAS_WIDTH,
        height: CANVAS_HEIGHT,
        backgroundColor: "#ffffff",
        preserveObjectStacking: true
    });

    // 2. Estado de la Aplicación
    const state = {
        model: 'V1',
        face: 'FRENTE',
        storage: '18',
    };

    const templates = {
        'V1-FRENTE': 'Fotos/DESCARTABLEV1FRENTE.png',
        'V1-DORSO': 'Fotos/DESCARTABLEV1DORSO.png',
        'V2-FRENTE': 'Fotos/DESCARTABLEV2FRENTE.png',
        'V2-DORSO': 'Fotos/DESCARTABLEV2DORSO.png'
    };

    const savedDesigns = {};
    let currentClipPath = null;

    // 3. Carga de Plantilla (Overlay)
    function loadTemplate(callback) {
        const key = `${state.model}-${state.face}`;
        const imgUrl = templates[key];

        if (!imgUrl) {
            if (callback) callback();
            return;
        }

        fabric.Image.fromURL(imgUrl, (img) => {
            if (!img) {
                if (callback) callback();
                return;
            }

            const scale = Math.min(CANVAS_WIDTH / img.width, CANVAS_HEIGHT / img.height) * 0.9;

            img.set({
                originX: 'center', originY: 'center',
                left: CANVAS_WIDTH / 2, top: CANVAS_HEIGHT / 2,
                scaleX: scale, scaleY: scale,
                evented: false, selectable: false
            });

            canvas.setOverlayImage(img, canvas.renderAll.bind(canvas));

            currentClipPath = new fabric.Rect({
                left: CANVAS_WIDTH / 2, top: CANVAS_HEIGHT / 2,
                originX: 'center', originY: 'center',
                width: img.width * scale, height: img.height * scale,
                absolutePositioned: true
            });

            canvas.getObjects().forEach(obj => { obj.clipPath = currentClipPath; });
            canvas.requestRenderAll();
            if (callback) callback();
        });
    }

    // 4. Cambio de Vista (Frente/Dorso o V1/V2)
    function switchView(newModel, newFace) {
        const currentKey = `${state.model}-${state.face}`;
        savedDesigns[currentKey] = canvas.getObjects().map(obj => obj.toObject());

        state.model = newModel;
        state.face = newFace;
        const newKey = `${state.model}-${state.face}`;

        canvas.clear();
        canvas.backgroundColor = '#ffffff';

        loadTemplate(() => {
            if (savedDesigns[newKey] && savedDesigns[newKey].length > 0) {
                fabric.util.enlivenObjects(savedDesigns[newKey], (objects) => {
                    objects.forEach(obj => {
                        obj.clipPath = currentClipPath;
                        canvas.add(obj);
                    });
                    canvas.renderAll();
                });
            }
        });
    }

    loadTemplate();

    // 5. Controles de UI (Botones laterales)
    document.querySelectorAll('.model-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.model-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            switchView(e.target.dataset.model, state.face);
        });
    });

    document.querySelectorAll('.face-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.face-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            switchView(state.model, e.target.dataset.face);
        });
    });

    document.querySelectorAll('.storage-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.storage-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            state.storage = e.target.dataset.storage;
        });
    });

    // 6. Subida de Imágenes
    const imageUpload = document.getElementById('imageUpload');
    imageUpload.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (f) {
            fabric.Image.fromURL(f.target.result, (img) => {
                const scale = 300 / Math.max(img.width, img.height);
                img.set({
                    left: CANVAS_WIDTH / 2, top: CANVAS_HEIGHT / 2,
                    originX: 'center', originY: 'center',
                    scaleX: scale, scaleY: scale,
                    cornerColor: '#ff2a85', transparentCorners: false,
                    clipPath: currentClipPath
                });
                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.requestRenderAll();
            });
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    // 7. Soporte para Borrar (Tecla Delete/Backspace)
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Delete' || e.key === 'Backspace') {
            const activeObj = canvas.getActiveObject();
            if (activeObj && e.target.tagName !== 'INPUT') {
                canvas.remove(activeObj);
            }
        }
    });

    // 8. FLUJO DE FINALIZACIÓN (Sidebar -> Modal)
    const finishBtn = document.getElementById('finishDesignBtn');
    const checkoutModal = document.getElementById('checkoutModal');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');

    finishBtn.addEventListener('click', () => {
        checkoutModal.style.display = 'flex';
    });

    // 9. LÓGICA DE GUARDADO (Confirmación en el Modal)
    confirmOrderBtn.addEventListener('click', async () => {
        const nameVal = document.getElementById('clientName').value;
        const emailVal = document.getElementById('clientEmail').value;
        const telVal = document.getElementById('clientWhatsapp').value;

        if (!nameVal || !emailVal || !telVal) {
            alert("Por favor, completá todos tus datos.");
            return;
        }

        confirmOrderBtn.innerHTML = 'Guardando pedido...';
        confirmOrderBtn.disabled = true;

        // Funciones de exportación interna
        const exportCleanCanvasBase64 = () => {
            const tempBg = canvas.backgroundColor;
            const tempOverlay = canvas.overlayImage;
            canvas.backgroundColor = null;
            canvas.overlayImage = null;
            canvas.renderAll();
            const dataUrl = canvas.toDataURL({ format: 'png', multiplier: 4 });
            canvas.backgroundColor = tempBg;
            canvas.overlayImage = tempOverlay;
            canvas.renderAll();
            return dataUrl;
        };

        const exportMockupCanvasBase64 = () => {
            const tempBg = canvas.backgroundColor;
            canvas.backgroundColor = null;
            canvas.renderAll();
            const dataUrl = canvas.toDataURL({ format: 'png', multiplier: 2 });
            canvas.backgroundColor = tempBg;
            canvas.renderAll();
            return dataUrl;
        };

        const generateCombinedMockup = async (frenteB64, dorsoB64) => {
            const c = document.createElement('canvas');
            c.width = 1000; c.height = 1500;
            const ctx = c.getContext('2d');
            // Fondo Rosa y Estrellas
            ctx.fillStyle = '#ff7bb4'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.fillStyle = '#faff60';
            const stars = [[100, 100, 20], [900, 150, 25], [150, 500, 15], [850, 800, 20], [100, 1200, 30]];
            stars.forEach(s => {
                ctx.beginPath();
                for (let i = 0; i < 5; i++) {
                    ctx.lineTo(s[0] + Math.cos((18 + i * 72) / 180 * Math.PI) * s[2], s[1] - Math.sin((18 + i * 72) / 180 * Math.PI) * s[2]);
                    ctx.lineTo(s[0] + Math.cos((54 + i * 72) / 180 * Math.PI) * (s[2] / 2.5), s[1] - Math.sin((54 + i * 72) / 180 * Math.PI) * (s[2] / 2.5));
                }
                ctx.closePath(); ctx.fill();
            });
            const loadImg = (src) => new Promise(res => { const img = new Image(); img.onload = () => res(img); img.src = src; });
            const fImg = await loadImg(frenteB64); const dImg = await loadImg(dorsoB64);
            ctx.drawImage(fImg, 100, 250, 800, fImg.height * (800 / fImg.width));
            ctx.drawImage(dImg, 100, 850, 800, dImg.height * (800 / dImg.width));
            return c.toDataURL('image/png');
        };

        const activeFace = state.face;
        const otherFace = activeFace === 'FRENTE' ? 'DORSO' : 'FRENTE';
        const activeKey = `${state.model}-${activeFace}`;
        const otherKey = `${state.model}-${otherFace}`;

        savedDesigns[activeKey] = canvas.getObjects().map(obj => obj.toObject());
        const activeClean = exportCleanCanvasBase64();
        const activeMock = exportMockupCanvasBase64();

        // Proceso silencioso para la otra cara
        canvas.clear();
        state.face = otherFace;
        let otherClean, otherMock;

        await new Promise(resolve => {
            loadTemplate(() => {
                if (savedDesigns[otherKey]) {
                    fabric.util.enlivenObjects(savedDesigns[otherKey], (objs) => {
                        objs.forEach(o => canvas.add(o));
                        canvas.renderAll();
                        otherClean = exportCleanCanvasBase64();
                        otherMock = exportMockupCanvasBase64();
                        resolve();
                    });
                } else { resolve(); }
            });
        });

        const finalMockup = await generateCombinedMockup(
            activeFace === 'FRENTE' ? activeMock : otherMock,
            activeFace === 'DORSO' ? activeMock : otherMock
        );

        const usedImages = [];
        [activeKey, otherKey].forEach(k => {
            (savedDesigns[k] || []).forEach(o => { if (o.type === 'image') usedImages.push(o.src); });
        });

        const payload = {
            modelo: state.model, storage: state.storage,
            nombre_cliente: nameVal, email: emailVal, whatsapp: telVal,
            imagen_frente: activeFace === 'FRENTE' ? activeClean : otherClean,
            imagen_dorso: activeFace === 'DORSO' ? activeClean : otherClean,
            mockup: finalMockup, imagenes_utilizadas: usedImages
        };

        try {
            const resp = await fetch('generar_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();
            if (res.success) {
                alert(`¡Pedido guardado! Orden: ${res.id_orden}`);
                checkoutModal.style.display = 'none';
            } else { alert("Error: " + res.error); }
        } catch (e) { alert("Error de conexión"); }

        confirmOrderBtn.innerHTML = originalText;
        confirmOrderBtn.disabled = false;
    });

    // 10. Zoom Controls
    let workspaceZoom = 1;
    document.getElementById('zoomInBtn').addEventListener('click', () => { if (workspaceZoom < 3) { workspaceZoom += 0.2; wrapper.style.transform = `scale(${workspaceZoom})`; document.getElementById('zoomVal').textContent = `${Math.round(workspaceZoom * 100)}%`; } });
    document.getElementById('zoomOutBtn').addEventListener('click', () => { if (workspaceZoom > 0.4) { workspaceZoom -= 0.2; wrapper.style.transform = `scale(${workspaceZoom})`; document.getElementById('zoomVal').textContent = `${Math.round(workspaceZoom * 100)}%`; } });
});