(function () {
    const root = document.querySelector('[data-passenger-camera]');
    const form = document.querySelector('[data-passenger-report-form]');
    const sessionCountdown = document.querySelector('[data-session-countdown]');

    function pluralize(value, singular, plural = `${singular}s`) {
        return `${value} ${value === 1 ? singular : plural}`;
    }

    function formatRemaining(seconds) {
        if (seconds <= 0) {
            return 'Expired';
        }

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        if (hours > 0) {
            return `${pluralize(hours, 'hour')} ${pluralize(minutes, 'minute')} remaining`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')} remaining`;
    }

    function startSessionCountdown() {
        if (!sessionCountdown) return;

        const expiresAt = Number.parseInt(sessionCountdown.dataset.expiresAt, 10) * 1000;
        const expiredRedirectUrl = sessionCountdown.dataset.expiredRedirect;

        if (!Number.isFinite(expiresAt)) return;

        let timer = null;
        const updateCountdown = () => {
            const secondsRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            sessionCountdown.textContent = formatRemaining(secondsRemaining);
            sessionCountdown.classList.toggle('is-urgent', secondsRemaining > 0 && secondsRemaining <= 60);
            sessionCountdown.classList.toggle('is-expired', secondsRemaining <= 0);

            if (secondsRemaining <= 0 && timer) {
                window.clearInterval(timer);
            }

            if (secondsRemaining <= 0 && expiredRedirectUrl) {
                window.location.assign(expiredRedirectUrl);
            }
        };

        updateCountdown();

        if (!sessionCountdown.classList.contains('is-expired')) {
            timer = window.setInterval(updateCountdown, 1000);
        }
    }

    startSessionCountdown();

    if (!root || !form) return;

    const video = root.querySelector('[data-camera-video]');
    const preview = root.querySelector('[data-camera-preview]');
    const empty = root.querySelector('[data-camera-empty]');
    const startButton = root.querySelector('[data-camera-start]');
    const captureButton = root.querySelector('[data-camera-capture]');
    const retakeButton = root.querySelector('[data-camera-retake]');
    const fallbackInput = root.querySelector('[data-camera-fallback]');
    const fallbackLabel = root.querySelector('[data-camera-fallback-label]');
    const message = root.querySelector('[data-camera-message]');
    const evidenceInput = form.querySelector('[data-passenger-evidence-input]');
    let stream = null;

    function setMessage(text, isError = false) {
        message.textContent = text;
        message.classList.toggle('is-error', isError);
    }

    function stopCamera() {
        if (!stream) return;
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    }

    function showCaptured(dataUrl) {
        evidenceInput.value = dataUrl;
        preview.src = dataUrl;
        preview.hidden = false;
        video.hidden = true;
        empty.hidden = true;
        captureButton.disabled = true;
        retakeButton.hidden = false;
        setMessage('Image captured and ready to submit.');
        stopCamera();
    }

    function compressImage(source, sourceWidth, sourceHeight) {
        const maxWidth = 1280;
        const maxHeight = 960;
        const scale = Math.min(1, maxWidth / sourceWidth, maxHeight / sourceHeight);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(sourceWidth * scale));
        canvas.height = Math.max(1, Math.round(sourceHeight * scale));
        canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/jpeg', 0.8);
    }

    async function startCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            fallbackLabel.hidden = false;
            setMessage('Live camera is unavailable. Use the device camera button.', true);
            return;
        }

        try {
            stopCamera();
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            video.srcObject = stream;
            video.hidden = false;
            preview.hidden = true;
            empty.hidden = true;
            captureButton.disabled = false;
            retakeButton.hidden = true;
            setMessage('Camera ready. Keep the bus and plate number visible.');
        } catch (error) {
            fallbackLabel.hidden = false;
            setMessage('Camera permission was not available. Use the device camera button.', true);
        }
    }

    startButton.addEventListener('click', startCamera);

    captureButton.addEventListener('click', () => {
        if (!video.videoWidth || !video.videoHeight) {
            setMessage('Wait for the camera preview, then capture again.', true);
            return;
        }

        showCaptured(compressImage(video, video.videoWidth, video.videoHeight));
    });

    retakeButton.addEventListener('click', () => {
        evidenceInput.value = '';
        preview.src = '';
        preview.hidden = true;
        empty.hidden = false;
        retakeButton.hidden = true;
        startCamera();
    });

    fallbackInput.addEventListener('change', () => {
        const file = fallbackInput.files?.[0];
        if (!file || !file.type.startsWith('image/')) {
            setMessage('Select a valid image from the device camera.', true);
            return;
        }

        const image = new Image();
        image.onload = () => {
            showCaptured(compressImage(image, image.naturalWidth, image.naturalHeight));
            URL.revokeObjectURL(image.src);
        };
        image.onerror = () => setMessage('The selected image could not be read.', true);
        image.src = URL.createObjectURL(file);
    });

    window.addEventListener('pagehide', stopCamera);
})();
