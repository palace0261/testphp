(function(){
  const scriptTag = document.currentScript || (function(){ const s = document.getElementsByTagName('script'); return s[s.length-1]; })();
  const threshold = parseFloat(scriptTag && scriptTag.dataset && scriptTag.dataset.threshold) || 0.5;
  const debounceMs = parseInt(scriptTag && scriptTag.dataset && scriptTag.dataset.debounce) || 200;

  document.addEventListener('DOMContentLoaded', function(){
    const videos = Array.from(document.querySelectorAll('video'));
    if(!videos.length) return;
    videos.forEach(v=>{ v.muted = true; v.setAttribute('playsinline',''); try{ v.pause(); }catch(e){} });

    const visibility = new WeakMap();
    let isScrolling = false;
    let scrollTimer = null;
    const obsThresholds = [0, 0.25, 0.5, 0.75, 1];

    const observer = new IntersectionObserver((entries)=>{
      entries.forEach(entry => {
        const v = entry.target;
        visibility.set(v, entry.intersectionRatio || 0);
        const needsStop = v.classList.contains('scroll-stop-play');
        const visibleEnough = (entry.intersectionRatio || 0) >= threshold;

        if(needsStop){
          if(!isScrolling && visibleEnough){
            const p = v.play(); if(p && p.catch) p.catch(()=>{});
          } else {
            try{ v.pause(); }catch(e){}
          }
        } else {
          if(visibleEnough){
            const p = v.play(); if(p && p.catch) p.catch(()=>{});
          } else {
            try{ v.pause(); }catch(e){}
          }
        }
      });
    }, { threshold: obsThresholds });

    videos.forEach(v=> observer.observe(v));

    window.addEventListener('scroll', ()=>{
      isScrolling = true;
      if(scrollTimer) clearTimeout(scrollTimer);

      // 즉시 일시정지: 스크롤-정지 조건이 필요한 비디오들
      videos.forEach(v=>{ if(v.classList.contains('scroll-stop-play')) try{ v.pause(); }catch(e){} });

      scrollTimer = setTimeout(()=>{
        isScrolling = false;
        // 스크롤 멈추면 화면에 충분히 보이는 scroll-stop 비디오만 재생
        videos.forEach(v=>{
          if(!v.classList.contains('scroll-stop-play')) return;
          if((visibility.get(v) || 0) >= threshold){ const p = v.play(); if(p && p.catch) p.catch(()=>{}); }
        });
      }, debounceMs);
    }, { passive: true });

    // 문서 숨김 처리 시 모든 비디오 일시정지
    document.addEventListener('visibilitychange', ()=>{
      if(document.hidden){ videos.forEach(v=>{ try{ v.pause(); }catch(e){} }); }
    });
  });
})();
