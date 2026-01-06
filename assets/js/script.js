document.addEventListener('DOMContentLoaded', () => {

    // DOM Elements
    const mainView = document.getElementById('mainView');
    const converterView = document.getElementById('converterView');
    const backBtn = document.getElementById('backBtn');
    const toolTitle = document.getElementById('toolTitle');
    const acceptText = document.getElementById('acceptText');

    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const uploadContent = uploadZone.querySelector('.upload-content');
    const filePreview = document.getElementById('filePreview');
    const previewThumb = document.getElementById('previewThumb');
    const fileNameDisplay = document.getElementById('fileName');
    const fileSizeDisplay = document.getElementById('fileSize');
    const formatSelect = document.getElementById('formatSelect');
    const convertBtn = document.getElementById('convertBtn');
    const progressBar = document.getElementById('progressBar');
    const progressBarContainer = document.querySelector('.progress-bar-container');
    const historyBody = document.getElementById('historyBody');
    const refreshHistoryBtn = document.getElementById('refreshHistory');

    let currentFile = null;
    let uploadedFileId = null;
    let currentMode = null; // e.g. 'jpg-to-pdf'
    let currentAccept = '*'; // e.g. '.pdf'

    // --- Mode Selection Utilities ---
    document.querySelectorAll('.tool-card').forEach(card => {
        card.addEventListener('click', () => {
            const mode = card.dataset.mode;
            const accept = card.dataset.accept;
            const title = card.querySelector('h3').textContent;

            enterConverterMode(mode, accept, title);
        });
    });

    // --- History / Navigation Handling ---

    // Handle Browser Back Button
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.view === 'converter') {
            // If we somehow pop INTO converter state (forward), ensure UI matches
            // enterConverterMode(event.state.mode, event.state.accept, event.state.title, false);
            // Simpler: Just reload if complexity arises, but for now let's handle it:
            mainView.style.display = 'none';
            converterView.style.display = 'block';
            // Ideally we'd need to re-set the title/accept from state if possible
        } else {
            // Default to Main View
            goBackToMain(false); // false = don't push state/back again
        }
    });

    backBtn.addEventListener('click', () => {
        // When clicking our UI back button, we want to go back in history
        // to keep the browser history clean.
        history.back();
    });

    const enterConverterMode = (mode, accept, title) => {
        currentMode = mode;
        currentAccept = accept;

        // Push State
        history.pushState({ view: 'converter', mode, accept, title }, '', `#${mode}`);

        mainView.style.display = 'none';
        converterView.style.display = 'block';

        toolTitle.textContent = title;
        acceptText.textContent = `Supported formats: ${accept.toUpperCase()}`;
        fileInput.accept = accept;

        // Reset any previous upload state
        resetUI();
    };

    const goBackToMain = (fromHistory = true) => {
        // Logic to actually switch the view
        resetUI();
        converterView.style.display = 'none';
        mainView.style.display = 'block';
        currentMode = null;

        // Clear hash if we are effectively back at root (optional cosmetic)
        if (!fromHistory) {
            // If called manually without history interaction (rare now), we might want to replaceState
            // history.replaceState(null, '', ' ');
        }
    };


    // --- Drag & Drop Utilities ---
    const preventDefaults = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'), false);
    });

    uploadZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    // --- File Handling ---
    const handleFiles = (files) => {
        if (files.length === 0) return;

        const file = files[0];

        // Size Validation
        if (file.size > 5 * 1024 * 1024) {
            showToast('File size exceeds 5MB limit', 'error');
            return;
        }

        // Type Validation based on Mode
        // Simple client-side check using extensions/mime
        // Note: 'image/*' is broad, specific checks below
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        // Allow if accept is * or matches extension or matches mime type part
        let valid = false;

        if (currentAccept === 'image/*') {
            if (file.type.startsWith('image/')) valid = true;
        } else {
            const accepted = currentAccept.split(',');
            if (accepted.includes(ext) || accepted.includes(file.type)) valid = true;
        }

        if (!valid) {
            showToast(`Invalid file type. Please upload ${currentAccept}`, 'error');
            return;
        }


        currentFile = file;
        showPreview(file);
        uploadFile(file);
    };

    const showPreview = (file) => {
        uploadContent.style.display = 'none';
        filePreview.style.display = 'block';

        fileNameDisplay.textContent = file.name;
        fileSizeDisplay.textContent = formatBytes(file.size);

        // Thumbnail
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => { previewThumb.src = e.target.result; };
            reader.readAsDataURL(file);
        } else {
            // Generic icons based on format
            if (file.name.endsWith('.pdf')) previewThumb.src = 'https://via.placeholder.com/60/FF0000/FFFFFF?text=PDF';
            else if (file.name.match(/\.(doc|docx)$/)) previewThumb.src = 'https://via.placeholder.com/60/2B579A/FFFFFF?text=WORD';
            else previewThumb.src = 'https://via.placeholder.com/60/CCCCCC/000000?text=FILE';
        }

        // Determine Target Selection automatically or prepopulate
        determineTargetFormat();
    };

    const determineTargetFormat = () => {
        // Logic to set target format in select or hidden field
        // based on currentMode
        formatSelect.innerHTML = '';
        let target = '';

        if (currentMode === 'jpg-to-pdf') target = 'pdf';
        else if (currentMode === 'pdf-to-jpg') target = 'jpg';
        else if (currentMode === 'ppt-to-pdf') target = 'pdf';
        else if (currentMode === 'pdf-to-ppt') target = 'ppt';
        else if (currentMode === 'pdf-to-word') target = 'doc';
        else if (currentMode === 'word-to-pdf') target = 'pdf';

        const option = document.createElement('option');
        option.value = target;
        option.textContent = target.toUpperCase();
        formatSelect.appendChild(option);
    };

    const formatBytes = (bytes, decimals = 2) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    };

    const populateFormatOptions = (mimeType) => {
        // Deprecated in favor of determineTargetFormat
    };

    // --- API Interactions ---
    const uploadFile = async (file) => {
        const formData = new FormData();
        formData.append('file', file);

        try {
            convertBtn.disabled = true;
            convertBtn.textContent = 'Uploading...';

            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (response.ok) {
                uploadedFileId = data.id;

                // Reset Button States for new conversion
                convertBtn.style.display = 'inline-block';
                convertBtn.disabled = false;
                convertBtn.textContent = 'Convert Now';

                const downloadBtn = document.getElementById('downloadBtn');
                if (downloadBtn) downloadBtn.style.display = 'none';

                showToast('File uploaded successfully', 'success');
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        } catch (error) {
            showToast(error.message, 'error');
            resetUI();
        }
    };

    convertBtn.addEventListener('click', async () => {
        if (!uploadedFileId) return;

        const targetFormat = formatSelect.value;
        if (!targetFormat) {
            showToast('Please select a target format', 'error');
            return;
        }

        progressBarContainer.style.display = 'block';
        convertBtn.disabled = true;
        progressBar.style.width = '0%';

        // Fake progress for UX
        let progress = 0;
        const interval = setInterval(() => {
            if (progress < 90) {
                progress += 10;
                progressBar.style.width = `${progress}%`;
            }
        }, 200);

        try {
            const response = await fetch('api/convert.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: uploadedFileId,
                    format_to: targetFormat
                })
            });

            const data = await response.json();
            clearInterval(interval);
            progressBar.style.width = '100%';

            if (response.ok) {
                showToast('Conversion successful!', 'success');
                loadHistory();

                // UI Updates
                convertBtn.style.display = 'none';

                const downloadBtn = document.getElementById('downloadBtn');
                // Use download.php to handle the unique path -> nice filename mapping
                downloadBtn.href = `download.php?id=${uploadedFileId}`;
                downloadBtn.style.display = 'block';
                downloadBtn.textContent = `Download ${data.converted_filename}`;
            } else {
                throw new Error(data.error || 'Conversion failed');
            }
        } catch (error) {
            clearInterval(interval);
            progressBar.style.width = '0%'; // Reset or Red
            showToast(error.message, 'error');
            convertBtn.disabled = false;
        }
    });

    // --- History ---
    const loadHistory = async (page = 1) => {
        try {
            const response = await fetch(`api/history.php?page=${page}`);
            const result = await response.json();

            renderHistory(result.data);
            // Handle pagination if needed
        } catch (error) {
            console.error('Failed to load history', error);
        }
    };

    const renderHistory = (items) => {
        historyBody.innerHTML = '';
        if (items.length === 0) {
            historyBody.innerHTML = '<tr><td colspan="6" style="text-align:center">No conversions yet</td></tr>';
            return;
        }

        items.forEach(item => {
            const tr = document.createElement('tr');

            let statusBadge = `<span class="status-badge status-${item.status}">${item.status}</span>`;
            let actionBtn = '-';

            if (item.status === 'completed') {
                actionBtn = `<a href="download.php?id=${item.id}" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-download"></i> Download</a>`;
            }

            // Calc size change
            let sizeInfo = formatBytes(item.original_size);
            if (item.converted_size) {
                const pct = Math.round(((item.converted_size - item.original_size) / item.original_size) * 100);
                const color = pct < 0 ? 'green' : 'red';
                const sign = pct > 0 ? '+' : '';
                sizeInfo += ` <span style="color:${color}; font-size:0.8em">(${sign}${pct}%)</span>`;
            }

            tr.innerHTML = `
                <td>${item.original_filename}</td>
                <td>${item.format_from.toUpperCase()} &rarr; ${item.format_to ? item.format_to.toUpperCase() : '?'}</td>
                <td>${sizeInfo}</td>
                <td>${new Date(item.upload_time).toLocaleTimeString()}</td>
                <td>${statusBadge}</td>
                <td>${actionBtn}</td>
            `;
            historyBody.appendChild(tr);
        });
    };

    refreshHistoryBtn.addEventListener('click', () => loadHistory());

    // --- Utilities ---
    const showToast = (msg, type = 'success') => {
        const toast = document.getElementById('toast');
        toast.textContent = msg;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    };

    const resetUI = () => {
        currentFile = null;
        uploadedFileId = null;
        fileInput.value = '';
        uploadContent.style.display = 'block';
        filePreview.style.display = 'none';
        progressBarContainer.style.display = 'none';

        // Reset Buttons
        convertBtn.disabled = false;
        convertBtn.textContent = 'Convert Now';
        convertBtn.style.display = 'inline-block'; // or block depending on CSS, typical btn is inline-block or block

        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) downloadBtn.style.display = 'none';
    };

    // Initial Load
    loadHistory();

    // Mobile Menu
    document.querySelector('.mobile-toggle').addEventListener('click', () => {
        const links = document.querySelector('.nav-links');
        links.style.display = links.style.display === 'flex' ? 'none' : 'flex';
        links.style.flexDirection = 'column';
        links.style.position = 'absolute';
        links.style.top = '60px';
        links.style.right = '0';
        links.style.background = 'white';
        links.style.width = '100%';
        links.style.boxShadow = '0 5px 5px rgba(0,0,0,0.1)';
        links.style.padding = '1rem';
    });
});
