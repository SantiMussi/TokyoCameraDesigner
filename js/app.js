document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Fabric.js Canvas
    const canvasEl = document.getElementById('cameraCanvas');
    const wrapper = document.querySelector('.canvas-wrapper');

    // We'll set a default size, but it will scale via CSS
    const CANVAS_WIDTH = 800;
    const CANVAS_HEIGHT = 600;

    const canvas = new fabric.Canvas('cameraCanvas', {
        width: CANVAS_WIDTH,
        height: CANVAS_HEIGHT,
        backgroundColor: "#ffffff", // Transparent to allow CSS glassmorphism to show through
        preserveObjectStacking: true // Keep objects in order when selecting
    });

    // 2. Application State
    const state = {
        model: 'V1',    // 'V1' or 'V2'
        face: 'FRENTE', // 'FRENTE' or 'DORSO'
        storage: '18',  // '18', '24' or '32'
    };

    // Keep track of the file paths for the masks/templates
    const templates = {
        'V1-FRENTE': 'Fotos/DESCARTABLEV1FRENTE.png',
        'V1-DORSO': 'Fotos/DESCARTABLEV1DORSO.png',
        'V2-FRENTE': 'Fotos/DESCARTABLEV2FRENTE.png',
        'V2-DORSO': 'Fotos/DESCARTABLEV2DORSO.png'
    };
    // Cache to store the user's designs for each face
    const savedDesigns = {};
    let currentClipPath = null; // Store the active clipping boundaries

    // 3. Load Template Image
    function loadTemplate(callback) {
        const key = `${state.model}-${state.face}`;
        const imgUrl = templates[key];

        // Ensure we don't break if URL is wrong
        if (!imgUrl) {
            if (callback) callback();
            return;
        }

        // Load the overlay mask
        // When using overlay image, user designs sit underneath the camera cutout.
        fabric.Image.fromURL(imgUrl, (img) => {
            if (!img) {
                if (callback) callback();
                return;
            }

            // Calculate scale to fit canvas width/height
            const scaleX = CANVAS_WIDTH / img.width;
            const scaleY = CANVAS_HEIGHT / img.height;
            const scale = Math.min(scaleX, scaleY) * 0.9; // 90% of canvas max

            img.set({
                originX: 'center',
                originY: 'center',
                left: CANVAS_WIDTH / 2,
                top: CANVAS_HEIGHT / 2,
                scaleX: scale,
                scaleY: scale,
                evented: false,   // Overlay shouldn't catch mouse events
                selectable: false // Can't be selected
            });

            // Set as overlay so it covers the elements added by user
            canvas.setOverlayImage(img, canvas.renderAll.bind(canvas));

            // Apply a clip path to the canvas to prevent uploaded images
            // from bleeding outside the boundaries of the camera mask bounds.
            currentClipPath = new fabric.Rect({
                left: CANVAS_WIDTH / 2,
                top: CANVAS_HEIGHT / 2,
                originX: 'center',
                originY: 'center',
                width: img.width * scale,
                height: img.height * scale,
                absolutePositioned: true
            });
            // We NO LONGER clip the canvas. We clip the individual objects to let controls overflow
            // canvas.clipPath = clipPath;

            canvas.getObjects().forEach(obj => {
                obj.clipPath = currentClipPath;
            });
            canvas.requestRenderAll();

            if (callback) callback();
        });
    }

    // Function to handle switching views safely, saving current objects
    function switchView(newModel, newFace) {
        const currentKey = `${state.model}-${state.face}`;
        // Save user-uploaded objects into the cache
        savedDesigns[currentKey] = canvas.getObjects().map(obj => obj.toObject());

        // Update application state
        state.model = newModel;
        state.face = newFace;
        const newKey = `${state.model}-${state.face}`;

        // Clear the canvas to be ready for the new view
        canvas.clear();
        canvas.backgroundColor = '#ffffff';

        // Load new overlay template, then conditionally restore cached objects
        loadTemplate(() => {
            if (savedDesigns[newKey] && savedDesigns[newKey].length > 0) {
                // enlivenObjects converts raw JSON object data back into Fabric.js object instances
                fabric.util.enlivenObjects(savedDesigns[newKey], (objects) => {
                    objects.forEach(obj => {
                        obj.clipPath = currentClipPath; // Re-apply the clipping to restored objects
                        canvas.add(obj);
                    });
                    canvas.renderAll();
                });
            }
        });
    }

    // Initial load
    loadTemplate();

    // 4. Handle UI Controls (Model Switch)
    const modelBtns = document.querySelectorAll('.model-btn');
    modelBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const selectedModel = e.target.dataset.model;
            if (selectedModel === state.model) return; // Do nothing if it's the same

            // Update UI
            modelBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');

            // Switch View
            switchView(selectedModel, state.face);
        });
    });

    // 5. Handle UI Controls (Face Switch)
    const faceBtns = document.querySelectorAll('.face-btn');
    faceBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const selectedFace = e.target.dataset.face;
            if (selectedFace === state.face) return; // Do nothing if it's the same

            // Update UI
            faceBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');

            // Switch View
            switchView(state.model, selectedFace);
        });
    });

    // 5b. Handle UI Controls (Storage Switch)
    const storageBtns = document.querySelectorAll('.storage-btn');
    storageBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const selectedStorage = e.target.dataset.storage;
            if (selectedStorage === state.storage) return;

            // Update UI
            storageBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');

            // Update State (no need to change the canvas for this)
            state.storage = selectedStorage;
        });
    });

    // 6. Handle Image Upload
    const imageUpload = document.getElementById('imageUpload');
    imageUpload.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (f) {
            const data = f.target.result;

            fabric.Image.fromURL(data, (img) => {
                // Initial scale so it doesn't overflow massively
                const maxDim = 300;
                let scale = 1;
                if (img.width > maxDim || img.height > maxDim) {
                    scale = maxDim / Math.max(img.width, img.height);
                }

                img.set({
                    left: CANVAS_WIDTH / 2,
                    top: CANVAS_HEIGHT / 2,
                    originX: 'center',
                    originY: 'center',
                    scaleX: scale,
                    scaleY: scale,
                    cornerColor: '#ff2a85',
                    cornerStrokeColor: '#fff',
                    borderColor: '#ff2a85',
                    transparentCorners: false
                });

                if (currentClipPath) {
                    img.clipPath = currentClipPath;
                }

                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.requestRenderAll();
            });
        };
        reader.readAsDataURL(file);

        // Reset input so the same file can be uploaded again if needed
        e.target.value = '';
    });

    // 7. Delete Object Support (Keyboard)
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Delete' || e.key === 'Backspace') {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                // Prevent going back in browser if backspace
                if (e.key === 'Backspace' && e.target.tagName !== 'INPUT') {
                    e.preventDefault();
                }
                canvas.remove(activeObj);
            }
        }
    });

    // 8. Output Design y Envío a PHP
    const finishBtn = document.getElementById('finishDesignBtn');
    finishBtn.addEventListener('click', async () => {
        // 1. Estado de Carga
        const originalText = finishBtn.innerHTML;
        finishBtn.innerHTML = 'Cargando...';
        finishBtn.disabled = true;

        // 2. Función para exportar un canvas limpiando el fondo temporalmente
        const exportCleanCanvasBase64 = () => {
            const tempBg = canvas.backgroundColor;
            const tempOverlay = canvas.overlayImage;

            canvas.backgroundColor = null;
            canvas.overlayImage = null;
            canvas.renderAll();

            const dataUrl = canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 4 // Alta resolución
            });

            canvas.backgroundColor = tempBg;
            canvas.overlayImage = tempOverlay;
            canvas.renderAll();

            return dataUrl;
        };

        const exportMockupCanvasBase64 = () => {
            const tempBg = canvas.backgroundColor;
            canvas.backgroundColor = null; // Para que el borde de la cámara sea transparente
            canvas.renderAll();

            const dataUrl = canvas.toDataURL({
                format: 'png',
                quality: 1,
                multiplier: 2 // Resolución moderada para mockup
            });

            canvas.backgroundColor = tempBg;
            canvas.renderAll();

            return dataUrl;
        };

        const generateCombinedMockup = async (frenteB64, dorsoB64) => {
            const c = document.createElement('canvas');
            c.width = 1000;
            c.height = 1600;
            const ctx = c.getContext('2d');

            // Fondo rosa
            ctx.fillStyle = '#ff7bb4';
            ctx.fillRect(0, 0, c.width, c.height);

            // Estrellitas amarillas
            ctx.fillStyle = '#faff60';
            const drawStar = (x, y, r) => {
                ctx.beginPath();
                for (let i = 0; i < 5; i++) {
                    ctx.lineTo(x + Math.cos((18 + i * 72) / 180 * Math.PI) * r, y - Math.sin((18 + i * 72) / 180 * Math.PI) * r);
                    ctx.lineTo(x + Math.cos((54 + i * 72) / 180 * Math.PI) * (r / 2.5), y - Math.sin((54 + i * 72) / 180 * Math.PI) * (r / 2.5));
                }
                ctx.closePath();
                ctx.fill();
            };

            const stars = [
                [100, 100, 25], [880, 180, 25], [120, 460, 22], [900, 680, 22],
                [80, 800, 20], [920, 1050, 25], [150, 1200, 30], [860, 1400, 20],
                [500, 40, 20], [550, 1550, 22], [200, 700, 15]
            ];
            stars.forEach(s => drawStar(s[0], s[1], s[2]));

            // Texto MOCK UP
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 50px "Arial Rounded MT Bold", "Varela Round", sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText("MOCK UP", 960, 90);

            const loadImg = (src) => new Promise(res => {
                if (!src) return res(null);
                const img = new Image(); img.onload = () => res(img); img.src = src;
            });
            const fImg = await loadImg(frenteB64);
            const dImg = await loadImg(dorsoB64);

            const targetW = 850;
            let targetH = 0;

            // Dibujar Frente en top
            if (fImg) {
                let scale = targetW / fImg.width;
                targetH = fImg.height * scale;
                ctx.drawImage(fImg, (c.width - targetW) / 2, 200, targetW, targetH);
            }

            // Dibujar Dorso abajo
            if (dImg) {
                let scale = targetW / dImg.width;
                let targetHDorso = dImg.height * scale;
                ctx.drawImage(dImg, (c.width - targetW) / 2, 200 + (targetH || 600) + 150, targetW, targetHDorso);
            }

            return c.toDataURL('image/png', 0.9);
        };

        // 3. Exportar cara activa (ambos formatos)
        const activeFace = state.face;
        const otherFace = activeFace === 'FRENTE' ? 'DORSO' : 'FRENTE';

        const activeFaceCleanBase64 = exportCleanCanvasBase64();
        const activeFaceMockupBase64 = exportMockupCanvasBase64();

        // 4. Movernos "silenciosamente" a la otra cara para exportarla
        const activeKey = `${state.model}-${activeFace}`;
        const otherKey = `${state.model}-${otherFace}`;
        savedDesigns[activeKey] = canvas.getObjects().map(obj => obj.toObject());

        canvas.clear();
        let otherFaceCleanBase64 = null;
        let otherFaceMockupBase64 = null;

        await new Promise((resolve) => {
            // Forzar estado temporal para cargar plantilla correctamente
            state.face = otherFace;
            loadTemplate(() => {
                if (savedDesigns[otherKey] && savedDesigns[otherKey].length > 0) {
                    fabric.util.enlivenObjects(savedDesigns[otherKey], (objects) => {
                        objects.forEach(obj => {
                            obj.clipPath = currentClipPath;
                            canvas.add(obj);
                        });
                        canvas.renderAll();
                        // Exportar ambos
                        otherFaceCleanBase64 = exportCleanCanvasBase64();
                        otherFaceMockupBase64 = exportMockupCanvasBase64();
                        resolve();
                    });
                } else {
                    canvas.renderAll();
                    otherFaceCleanBase64 = exportCleanCanvasBase64();
                    otherFaceMockupBase64 = exportMockupCanvasBase64();
                    resolve();
                }
            });
        });

        // Restaurar estado de cara original
        state.face = activeFace;

        // 5. Restaurar la vista original que estaba viendo el usuario
        canvas.clear();
        canvas.backgroundColor = '#ffffff';
        loadTemplate(() => {
            if (savedDesigns[activeKey] && savedDesigns[activeKey].length > 0) {
                fabric.util.enlivenObjects(savedDesigns[activeKey], (objects) => {
                    objects.forEach(obj => {
                        obj.clipPath = currentClipPath; // Reasignar clip
                        canvas.add(obj);
                    });
                    canvas.renderAll();
                });
            }
        });

        // 5.5 Generar el Mockup Combinado
        const frenteMockup = activeFace === 'FRENTE' ? activeFaceMockupBase64 : otherFaceMockupBase64;
        const dorsoMockup = activeFace === 'DORSO' ? activeFaceMockupBase64 : otherFaceMockupBase64;
        const finalMockupBase64 = await generateCombinedMockup(frenteMockup, dorsoMockup);

        // 5.6 Extraer las imágenes originales subidas por el usuario (una por una)
        const usedImages = [];
        ['FRENTE', 'DORSO'].forEach(face => {
            const key = `${state.model}-${face}`;
            const objects = savedDesigns[key] || [];
            objects.forEach(obj => {
                // Agregar solo las imágenes que el usuario subió
                if (obj.type === 'image' && obj.src) {
                    usedImages.push(obj.src);
                }
            });
        });

        // 6. Preparar JSON y enviar al backend PHP
        const payload = {
            modelo: state.model,
            storage: state.storage,
            // Guardamos las limpiezas por las dudas
            imagen_frente: activeFace === 'FRENTE' ? activeFaceCleanBase64 : otherFaceCleanBase64,
            imagen_dorso: activeFace === 'DORSO' ? activeFaceCleanBase64 : otherFaceCleanBase64,
            mockup: finalMockupBase64,
            imagenes_utilizadas: usedImages
        };

        try {
            const response = await fetch('generar_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                alert('¡Diseño guardado correctamente en tu carpeta de pedidos!');
                console.log('Carpeta del pedido:', result.folder_url);
            } else {
                alert('Hubo un error en el servidor: ' + result.error);
                console.error(result.error);
            }
        } catch (error) {
            console.error("Error en el Fetch:", error);
            alert("Error conectando con generar_pdf.php");
        } finally {
            finishBtn.innerHTML = originalText;
            finishBtn.disabled = false;
        }
    });

    // 9. Workspace Zoom Controls
    let workspaceZoom = 1;
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomVal = document.getElementById('zoomVal');

    function updateWorkspaceZoom() {
        wrapper.style.transform = `scale(${workspaceZoom})`;
        zoomVal.textContent = `${Math.round(workspaceZoom * 100)}%`;
    }

    zoomInBtn.addEventListener('click', () => {
        if (workspaceZoom < 3) { // Max 300%
            workspaceZoom += 0.2;
            updateWorkspaceZoom();
        }
    });

    zoomOutBtn.addEventListener('click', () => {
        if (workspaceZoom > 0.4) { // Min 40%
            workspaceZoom -= 0.2;
            updateWorkspaceZoom();
        }
    });

    zoomVal.addEventListener('click', () => {
        // Reset zoom on clicking percentage
        workspaceZoom = 1;
        updateWorkspaceZoom();
    });
    // Change cursor style to pointer to indicate it's clickable
    zoomVal.style.cursor = 'pointer';

    // 10. Responsive Canvas Support
    window.addEventListener('resize', () => {
        // Recalculate pointer coordinates whenever the CSS window size changes
        canvas.calcOffset();
    });

});
