/**
 * tracking.ts — 動態載入追蹤碼（GA4 / FB Pixel / GTM）
 *
 * 追蹤碼由管理員在後台 SeoPage 設定，透過公開端點 /api/v1/site-config 取得。
 * 載入失敗不影響網站功能（fire-and-forget）。
 */

interface TrackingConfig {
  ga_measurement_id: string | null
  fb_pixel_id: string | null
  gtm_id: string | null
}

let loaded = false

export async function initTracking(): Promise<void> {
  if (loaded) return
  loaded = true

  try {
    const base = import.meta.env.VITE_API_BASE_URL || '/api/v1'
    const res = await fetch(`${base}/site-config`)
    if (!res.ok) return
    const json = await res.json()
    const tracking: TrackingConfig = json?.data?.tracking ?? {
      ga_measurement_id: null, fb_pixel_id: null, gtm_id: null,
    }

    if (tracking.ga_measurement_id) loadGA4(tracking.ga_measurement_id)
    if (tracking.fb_pixel_id) loadFBPixel(tracking.fb_pixel_id)
    if (tracking.gtm_id) loadGTM(tracking.gtm_id)
  } catch (err) {
    console.warn('[tracking] init failed:', err)
  }
}

function loadGA4(measurementId: string): void {
  if (document.querySelector('script[src*="googletagmanager.com/gtag"]')) return

  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`
  document.head.appendChild(script)

  const initScript = document.createElement('script')
  initScript.textContent = `
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    window.gtag = gtag;
    gtag('js', new Date());
    gtag('config', '${measurementId}', { send_page_view: true });
  `
  document.head.appendChild(initScript)
}

function loadFBPixel(pixelId: string): void {
  if (document.querySelector('script[src*="connect.facebook.net"]')) return

  const script = document.createElement('script')
  script.textContent = `
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '${pixelId}');
    fbq('track', 'PageView');
  `
  document.head.appendChild(script)
}

function loadGTM(gtmId: string): void {
  if (document.querySelector('script[src*="googletagmanager.com/gtm.js"]')) return

  const script = document.createElement('script')
  script.textContent = `
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','${gtmId}');
  `
  document.head.appendChild(script)
}

/**
 * SPA 頁面切換追蹤（Hash mode + router.afterEach 呼叫）
 * GA4 預設只追蹤第一次 page load，SPA 切換要手動 event
 */
export function trackPageView(path: string): void {
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'page_view', {
      page_path: path,
      page_title: document.title,
      page_location: window.location.href,
    })
  }
  if (typeof window.fbq === 'function') {
    window.fbq('track', 'PageView')
  }
}
