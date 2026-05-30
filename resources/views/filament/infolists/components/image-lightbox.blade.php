<!-- Ditambahkan fungsi watermark menggunakan gambar logo -->
<div x-data="{ 
        isOpen: false, 
        mediaSrc: '', 
        mediaType: 'image',
        checkMedia(src) {
            this.mediaSrc = src;
            if (src.match(/\.(mp4|webm|ogg|mov|3gp)($|\?)/i)) {
                this.mediaType = 'video';
            } else {
                this.mediaType = 'image';
            }
        },
        downloadWithImageWatermark() {
            // Jika media berupa video, langsung download file asli
            if (this.mediaType === 'video') {
                const a = document.createElement('a');
                a.href = this.mediaSrc;
                a.download = 'video_' + Date.now() + '.mp4';
                a.click();
                return;
            }

            // 1. Load Foto Utama
            const mainImg = new Image();
            mainImg.crossOrigin = 'anonymous';
            mainImg.src = this.mediaSrc;
            
            mainImg.onload = () => {
                // 2. Load Gambar Logo Watermark
                const watermarkImg = new Image();
                watermarkImg.crossOrigin = 'anonymous';
                
                // GANTI URL DI BAWAH INI DENGAN PATH LOGO KAMU (bisa url public asset Laravel)
                watermarkImg.src = '{{ asset('images/hm-logo.webp') }}'; 
                
                watermarkImg.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Set ukuran canvas sesuai foto utama
                    canvas.width = mainImg.width;
                    canvas.height = mainImg.height;
                    
                    // Gambar foto utama ke canvas
                    ctx.drawImage(mainImg, 0, 0);
                    
                    // --- KONFIGURASI UKURAN LOGO WATERMARK (Proporsional) ---
                    // Misalnya kita ingin lebar logo adalah 15% dari lebar foto utama
                    const watermarkWidth = mainImg.width * 0.15;
                    // Hitung tinggi secara proporsional sesuai rasio asli logo
                    const scaleRatio = watermarkWidth / watermarkImg.width;
                    const watermarkHeight = watermarkImg.height * scaleRatio;
                    
                    // --- TENTUKAN POSISI (Pojok Kanan Bawah) ---
                    const padding = mainImg.width * 0.02; // Jarak margin 2% dari tepi
                    const x = canvas.width - watermarkWidth - padding;
                    const y = canvas.height - watermarkHeight - padding;
                    
                    // Atur transparansi logo (0.0 = transparan total, 1.0 = padat/solid)
                    ctx.globalAlpha = 0.6; 
                    
                    // Tempel gambar logo ke atas foto utama
                    ctx.drawImage(watermarkImg, x, y, watermarkWidth, watermarkHeight);
                    
                    // Reset kembali alpha ke normal untuk proses export
                    ctx.globalAlpha = 1.0;
                    
                    // Eksekusi download hasil kombinasi
                    const dataURL = canvas.toDataURL('image/jpeg', 0.9);
                    const link = document.createElement('a');
                    link.href = dataURL;
                    link.download = 'watermarked_' + Date.now() + '.jpg';
                    link.click();
                };

                // Antisipasi jika file logo gagal dimuat (tetap download foto asli)
                watermarkImg.onerror = () => {
                    const link = document.createElement('a');
                    link.href = this.mediaSrc;
                    link.download = 'photo_' + Date.now() + '.jpg';
                    link.click();
                };
            };
        }
     }" x-on:open-preview-modal.window="isOpen = true; checkMedia($event.detail.src)"
    x-on:keydown.escape.window="isOpen = false" class="relative z-50" :class="{ 'pointer-events-none': !isOpen }" x-cloak>

    <!-- Overlay Gelap -->
    <div class="fixed inset-0 bg-black/85 backdrop-blur-md transition-opacity" x-show="isOpen" x-transition.opacity
        x-on:click="isOpen = false"></div>

    <!-- Jendela Popup -->
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4" x-show="isOpen">

        <!-- Container Utama Media + Tombol -->
        <div class="relative max-w-4xl w-full flex flex-col items-center" x-show="isOpen"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

            <!-- Pembungkus Relatif Agar Tombol Mengikuti Pojok Media -->
            <div class="relative inline-block max-h-[85vh] max-w-full">

                <!-- GRUP TOMBOL AKSI -->
                <div class="absolute -top-4 -right-4 z-50 flex items-center gap-2" style="pointer-events: auto;">

                    <!-- TOMBOL DOWNLOAD (Mengaktifkan fungsi gambar watermark) -->
                    <button type="button" x-on:click="downloadWithImageWatermark()"
                        style="pointer-events: auto; background-color: #3b82f6;"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg ring-4 ring-white transition duration-200 ease-in-out hover:bg-blue-700 hover:scale-110 active:scale-95 dark:ring-gray-900 focus:outline-none"
                        title="Download File">
                        <svg class="w-5 h-5 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </button>

                    <!-- TOMBOL CLOSE -->
                    <button type="button" x-on:click="isOpen = false" style="background: red;"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-white shadow-lg ring-4 ring-white transition duration-200 ease-in-out hover:bg-red-700 hover:scale-110 active:scale-95 dark:ring-gray-900 focus:outline-none"
                        title="Tutup (Esc)">
                        <svg class="w-5 h-5 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- KONDISI 1: GAMBAR -->
                <template x-if="mediaType === 'image'">
                    <img :src="mediaSrc" x-on:click.away="isOpen = false" style="
            max-height: 85vh; 
            max-width: 100%; 
            width: auto; 
            height: auto; 
            object-fit: contain; 
            display: block; 
            margin: 0 auto;
            border-radius: 12px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
            border: 1px solid rgba(229, 231, 235, 0.2);
         ">
                </template>

                <!-- KONDISI 2: VIDEO -->
                <template x-if="mediaType === 'video'">
                    <video :src="mediaSrc" controls autoplay x-on:click.away="isOpen = false"
                        class="max-h-[85vh] max-w-full rounded-xl shadow-2xl border border-gray-200/20 dark:border-gray-800 object-contain block focus:outline-none">
                    </video>
                </template>

            </div>

        </div>
    </div>
</div>