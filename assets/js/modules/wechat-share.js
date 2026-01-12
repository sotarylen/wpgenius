// 配置 JS-SDK 并注入分享卡片
(async () => {
    const url = location.href.split('#')[0];
    const res = await fetch(`/wp-json/wechat/sign?url=${encodeURIComponent(url)}`);
    const data = await res.json();
    if (!data.appId) return;

    wx.config({
        debug: false,
        appId: data.appId,
        timestamp: data.timestamp,
        nonceStr: data.nonceStr,
        signature: data.signature,
        jsApiList: ['updateAppMessageShareData', 'updateTimelineShareData']
    });

    const title = document.querySelector('meta[property="og:title"]')?.content || document.title;
    const desc  = document.querySelector('meta[property="og:description"]')?.content || '';
    const img   = document.querySelector('meta[property="og:image"]')?.content || '';

    wx.ready(() => {
        const opts = { title, desc, link: url, imgUrl: img };
        wx.updateAppMessageShareData(opts);
        wx.updateTimelineShareData(opts);
    });
})();