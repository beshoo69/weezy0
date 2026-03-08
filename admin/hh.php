
<script>
    /* 
 * سكريبت شاشة الترحيب السينمائي (Mobile Only - Robust)
 * النسخة: 11.0 - معالجة عدم الظهور في الشاشات الصغيرة وتحسين التحميل.
 */
(function () {
    const videoUrl = "https://i.top4top.io/m_3706jsyfb1.mp4";
    const bgPoster = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

    function initMobileSplash() {
        // 1. فحص حجم الشاشة بدقة أكبر (يشمل العرض الفعلي وعرض المتصفح)
        const currentWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;

        // إذا كانت الشاشة كبيرة (أكبر من 992بكسل) نتوقف - رفعنا الحد قليلاً لضمان عملها على التابلت والجوال
        if (currentWidth > 992) {
            return;
        }

        if (document.getElementById('splash-screen') || !document.body) return;

        const style = document.createElement('style');
        style.textContent = `
            #splash-screen {
                position: fixed;
                top: 0; left: 0;
                width: 100vw; height: 100vh;
                background-color: #000000;
                display: flex; justify-content: center; align-items: center;
                z-index: 2147483647;
                transition: opacity 0.8s ease-out, visibility 0.8s;
                overflow: hidden;
            }
            .splash-wrapper {
                width: 100%; height: 100%;
                display: flex; justify-content: center; align-items: center;
                pointer-events: none;
            }
            #splash-video-mob {
                width: 100%; height: 100%;
                object-fit: contain; 
                background-color: #000;
                transform: scale(1.6); 
                opacity: 0; 
                transition: opacity 0.5s ease-in;
            }
            video::-webkit-media-controls { display: none !important; }
            .mob-active { opacity: 1 !important; }
            .splash-out { opacity: 0; visibility: hidden; }
        `;
        document.head.appendChild(style);

        const splash = document.createElement('div');
        splash.id = 'splash-screen';
        splash.innerHTML = `
            <div class="splash-wrapper">
                <video id="splash-video-mob" autoplay muted loop playsinline 
                    webkit-playsinline poster="${bgPoster}" preload="auto"
                    disablePictureInPicture disableRemotePlayback>
                    <source src="${videoUrl}" type="video/mp4">
                </video>
            </div>
        `;
        document.body.appendChild(splash);
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        const videoElem = document.getElementById('splash-video-mob');
        let done = false;

        const cleanup = () => {
            if (done) return;
            done = true;
            splash.classList.add('splash-out');
            setTimeout(() => {
                splash.remove();
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
            }, 800);
        };

        const activate = () => videoElem.classList.add('mob-active');

        videoElem.addEventListener('playing', activate);
        videoElem.addEventListener('timeupdate', () => { if (videoElem.currentTime > 0) activate(); });

        // محاولة تشغيل إجبارية
        videoElem.play().then(activate).catch(() => { });

        // أمان: زدنا الوقت إلى 6 ثوانٍ لأن الإنترنت في الجوال قد يكون أبطأ
        // إذا لم يعمل الفيديو خلال 6 ثوانٍ، سيفتح الموقع تلقائياً
        setTimeout(() => {
            if (!videoElem.classList.contains('mob-active')) cleanup();
        }, 6000);

        setTimeout(cleanup, 10000); // الإخفاء النهائي بعد الوقت المحدد
        videoElem.onerror = cleanup;
    }

    // التشغيل المباشر لضمان عدم الضياع
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileSplash);
    } else {
        initMobileSplash();
    }
})();

</script>