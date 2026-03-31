/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Cualquier copia no autorizada será reportada.
*/
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
        quantity: 1
    };

    // Lógica selector de cantidad
    const btnMinus = document.getElementById('btnMinus');
    const btnPlus = document.getElementById('btnPlus');
    const qtyDisplay = document.getElementById('qtyDisplay');

    if (btnMinus && btnPlus && qtyDisplay) {
        btnMinus.addEventListener('click', (e) => {
            e.preventDefault();
            if (state.quantity > 1) {
                state.quantity--;
                qtyDisplay.textContent = state.quantity;
            }
        });
        btnPlus.addEventListener('click', (e) => {
            e.preventDefault();
            state.quantity++;
            qtyDisplay.textContent = state.quantity;
        });
    }

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

    // 5. Controles de UI (Modelos, Caras y Cantidad)
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
            const tempImg = new Image();
            tempImg.onload = function () {
                if (tempImg.width < 1500 || tempImg.height < 1500) {
                    showToast("⚠️ Esta imagen tiene baja resolución. Podría salir pixelada al imprimir.");
                }
                fabric.Image.fromURL(f.target.result, (img) => {
                    const scale = 300 / Math.max(img.width, img.height);
                    img.set({
                        left: CANVAS_WIDTH / 2, top: CANVAS_HEIGHT / 2,
                        originX: 'center', originY: 'center',
                        scaleX: scale, scaleY: scale,
                        cornerColor: '#ff2a85', transparentCorners: false,
                        clipPath: currentClipPath,
                        originalScale: scale
                    });
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.requestRenderAll();
                });
            };
            tempImg.src = f.target.result;
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    // -- TOAST FUNCTIONALITY --
    function showToast(message) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        toast.style.background = '#333';
        toast.style.color = 'white';
        toast.style.padding = '12px 20px';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.style.fontSize = '14px';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.innerText = message;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 10);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // -- ALERT & CONFIRM MODALS --
    function customAlert(msg, title = 'Atención', icon = '⚠️') {
        const modal = document.getElementById('customAlert');
        if (!modal) { alert(msg); return; }
        document.getElementById('customAlertTitle').innerText = title;
        document.getElementById('customAlertMsg').innerText = msg;
        document.getElementById('customAlertIcon').innerText = icon;
        modal.style.display = 'flex';
    }

    function customConfirm(msg, onConfirm, title = 'Confirmar', icon = '❓') {
        const modal = document.getElementById('customConfirm');
        if (!modal) { if (confirm(msg)) onConfirm(); return; }
        document.getElementById('customConfirmTitle').innerText = title;
        document.getElementById('customConfirmMsg').innerText = msg;
        document.getElementById('customConfirmIcon').innerText = icon;
        modal.style.display = 'flex';

        document.getElementById('customConfirmOk').onclick = function () {
            modal.style.display = 'none';
            onConfirm();
        };
        document.getElementById('customConfirmCancel').onclick = function () {
            modal.style.display = 'none';
        };
    }

    // -- CONTROLES DE CAPAS Y ELIMINACIÓN --
    const objControls = document.getElementById('objectControls');
    canvas.on('selection:created', () => { if (objControls) objControls.style.display = 'block'; });
    canvas.on('selection:updated', () => { if (objControls) objControls.style.display = 'block'; });
    canvas.on('selection:cleared', () => { if (objControls) objControls.style.display = 'none'; });

    document.getElementById('bringForwardBtn')?.addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) { canvas.bringForward(activeObj); canvas.requestRenderAll(); }
    });

    document.getElementById('sendBackwardBtn')?.addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) { canvas.sendBackwards(activeObj); canvas.requestRenderAll(); }
    });

    document.getElementById('resetProportionsBtn')?.addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type === 'image') {
            if (activeObj.originalScale) {
                activeObj.set({ scaleX: activeObj.originalScale, scaleY: activeObj.originalScale });
            } else {
                activeObj.set({ scaleY: activeObj.scaleX });
            }
            activeObj.setCoords();
            canvas.requestRenderAll();
        }
    });

    document.getElementById('deleteObjBtn')?.addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) { canvas.remove(activeObj); canvas.discardActiveObject(); canvas.requestRenderAll(); }
    });

    document.getElementById('clearDesignBtn')?.addEventListener('click', () => {
        customConfirm('¿Estás seguro de que deseas limpiar el diseño actual?', () => {
            const objects = canvas.getObjects();
            objects.forEach(obj => {
                if (obj !== canvas.overlayImage && obj.type !== 'rect') {
                    canvas.remove(obj);
                }
            });
            canvas.backgroundColor = '#ffffff';
            canvas.discardActiveObject();
            canvas.requestRenderAll();
        }, 'Limpiar Canvas', '🧹');
    });

    // 7. Borrar objetos con teclado
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Delete' || e.key === 'Backspace') {
            const activeObj = canvas.getActiveObject();
            if (activeObj && e.target.tagName !== 'INPUT') {
                canvas.remove(activeObj);
            }
        }
    });

    // 8. FLUJO DE FINALIZACIÓN (Side bar abre el Modal)
    const finishBtn = document.getElementById('finishDesignBtn');
    const checkoutModal = document.getElementById('checkoutModal');
    const confirmOrderBtn = document.getElementById('confirmOrderBtn');

    finishBtn.addEventListener('click', () => {
        let hasImages = false;

        // Revisar canvas actual
        canvas.getObjects().forEach(obj => {
            if (obj.type === 'image') hasImages = true;
        });

        // Revisar diseños guardados (otras vistas)
        Object.values(savedDesigns).forEach(designObjs => {
            designObjs.forEach(obj => {
                if (obj.type === 'image') hasImages = true;
            });
        });

        if (!hasImages) {
            customAlert("Por favor, subí al menos una foto al diseño para continuar.", "Diseño vacío", "📸");
            return;
        }

        checkoutModal.style.display = 'flex';
    });

    // 9. LÓGICA DE GUARDADO (Botón dentro del Modal)
    confirmOrderBtn.addEventListener('click', async () => {
        const nameVal = document.getElementById('clientName').value.trim();
        const emailVal = document.getElementById('clientEmail').value.trim();
        const telVal = document.getElementById('clientWhatsapp').value.trim();

        if (!nameVal || !emailVal || !telVal) {
            customAlert("Por favor, completá todos tus datos.", "Faltan datos", "📝");
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailVal)) {
            customAlert("Por favor, ingresá un correo electrónico válido.", "Email inválido", "📧");
            return;
        }

        confirmOrderBtn.innerHTML = 'Guardando pedido...';
        confirmOrderBtn.disabled = true;

        const loader = document.getElementById('loadingOverlay');
        if (loader) loader.style.display = 'flex';

        // Exportar cara limpia para impresión
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

        // Exportar cara con cámara para Mockup
        const exportMockupCanvasBase64 = () => {
            const tempBg = canvas.backgroundColor;
            canvas.backgroundColor = null;
            canvas.renderAll();
            const dataUrl = canvas.toDataURL({ format: 'png', multiplier: 2 });
            canvas.backgroundColor = tempBg;
            canvas.renderAll();
            return dataUrl;
        };

        // Generar Mockup combinado en FONDO BLANCO
        const generateCombinedMockup = async (frenteB64, dorsoB64) => {
            const c = document.createElement('canvas');
            c.width = 1000; c.height = 1600;
            const ctx = c.getContext('2d');

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, c.width, c.height);

            const loadImg = (src) => new Promise(res => {
                if (!src) return res(null);
                const img = new Image(); img.onload = () => res(img); img.src = src;
            });

            const fImg = await loadImg(frenteB64);
            const dImg = await loadImg(dorsoB64);

            const targetW = 850;
            if (fImg) {
                let scale = targetW / fImg.width;
                ctx.drawImage(fImg, (c.width - targetW) / 2, 200, targetW, fImg.height * scale);
            }
            if (dImg) {
                let scale = targetW / dImg.width;
                ctx.drawImage(dImg, (c.width - targetW) / 2, 900, targetW, dImg.height * scale);
            }
            return c.toDataURL('image/png', 0.9);
        };

        const activeFace = state.face;
        const otherFace = activeFace === 'FRENTE' ? 'DORSO' : 'FRENTE';
        const activeKey = `${state.model}-${activeFace}`;
        const otherKey = `${state.model}-${otherFace}`;

        // Guardar diseño actual
        savedDesigns[activeKey] = canvas.getObjects().map(obj => obj.toObject());
        const activeClean = exportCleanCanvasBase64();
        const activeMock = exportMockupCanvasBase64();

        // Proceso silencioso para exportar la otra cara
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

        // Generar Mockup Final
        const finalMockup = await generateCombinedMockup(
            activeFace === 'FRENTE' ? activeMock : otherMock,
            activeFace === 'DORSO' ? activeMock : otherMock
        );

        // Capturar imágenes originales individuales
        const usedImages = [];
        [activeKey, otherKey].forEach(k => {
            (savedDesigns[k] || []).forEach(o => { if (o.type === 'image') usedImages.push(o.src); });
        });

        const payload = {
            modelo: state.model, storage: state.storage, cantidad: state.quantity,
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
                if (loader) loader.style.display = 'none';
                customAlert(`¡Pedido guardado exitosamente! Tu orden es: ${res.id_orden}`, "¡Éxito!", "🎉");
                checkoutModal.style.display = 'none';
            } else {
                if (loader) loader.style.display = 'none';
                customAlert("Error: " + res.error, "Error al guardar", "❌");
            }
        } catch (e) {
            if (loader) loader.style.display = 'none';
            customAlert("Error de conexión con el servidor", "Error de red", "🔌");
        }

        confirmOrderBtn.innerHTML = 'GUARDAR Y FINALIZAR';
        confirmOrderBtn.disabled = false;
    });

    // 10. Controles de Zoom
    let workspaceZoom = 1;
    document.getElementById('zoomInBtn').addEventListener('click', () => { if (workspaceZoom < 3) { workspaceZoom += 0.2; wrapper.style.transform = `scale(${workspaceZoom})`; document.getElementById('zoomVal').textContent = `${Math.round(workspaceZoom * 100)}%`; } });
    document.getElementById('zoomOutBtn').addEventListener('click', () => { if (workspaceZoom > 0.4) { workspaceZoom -= 0.2; wrapper.style.transform = `scale(${workspaceZoom})`; document.getElementById('zoomVal').textContent = `${Math.round(workspaceZoom * 100)}%`; } });

    window.addEventListener('resize', () => { canvas.calcOffset(); });
});