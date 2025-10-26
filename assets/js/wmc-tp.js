(function(){
  // Util: throttle RAF
  const raf = window.requestAnimationFrame || function(fn){ return setTimeout(fn,16); };

  function initCarousel(root){
    const track   = root.querySelector('.wmc-tp-track');
    if (!track) return;

    const slides  = Array.from(track.children);
    if (!slides.length) return;

    const autoplayMs = parseInt(root.getAttribute('data-autoplay'), 10) || 0;

    // ---- Autoplay ----
    let autoTimer = null;
    const startAutoplay = () => {
      if (!autoplayMs) return;
      stopAutoplay();
      autoTimer = setInterval(()=> step(+1), autoplayMs);
    };
    const stopAutoplay = () => { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } };

    // ---- Snap helpers ----
    const getNearestIndex = () => {
      const scrollLeft = track.scrollLeft;
      // hallamos el slide más cercano al inicio del viewport del track
      let bestIdx = 0, bestDist = Infinity;
      for (let i=0;i<slides.length;i++){
        const off = slides[i].offsetLeft;
        const dist = Math.abs(off - scrollLeft);
        if (dist < bestDist){ bestDist = dist; bestIdx = i; }
      }
      return bestIdx;
    };

    const scrollToIndex = (idx, behavior='smooth') => {
      idx = Math.max(0, Math.min(slides.length - 1, idx));
      const x = slides[idx].offsetLeft;
      track.scrollTo({ left: x, behavior });
    };

    const step = (dir)=> {
      const current = getNearestIndex();
      scrollToIndex(current + dir);
    };

    // ---- Pointer drag (desktop y táctil con Pointer Events) ----
    let dragging = false;
    let startX = 0;
    let startScroll = 0;
    let lastX = 0;
    let lastT = 0;
    let velocity = 0;

    // Mejora de UX: cursor "grabbing" si hay CSS de apoyo
    const setDraggingClass = (on)=> {
      if (on) root.classList.add('wmc-tp--dragging');
      else root.classList.remove('wmc-tp--dragging');
    };

    const onPointerDown = (e) => {
      // solo botón principal si es mouse
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      dragging = true;
      root.setPointerCapture?.(e.pointerId);
      stopAutoplay();
      startX = e.clientX;
      startScroll = track.scrollLeft;
      lastX = startX;
      lastT = performance.now();
      velocity = 0;
      setDraggingClass(true);
      e.preventDefault();
    };

    const onPointerMove = (e) => {
      if (!dragging) return;
      const x = e.clientX;
      const dx = x - startX;
      track.scrollLeft = startScroll - dx;

      // velocidad (px/ms) para inercia mínima
      const now = performance.now();
      const dt = Math.max(1, now - lastT);
      velocity = (x - lastX) / dt;
      lastX = x;
      lastT = now;

      e.preventDefault();
    };

    const onPointerUp = (e) => {
      if (!dragging) return;
      dragging = false;
      setDraggingClass(false);

      // Inercia muy ligera + snap
      const boost = Math.abs(velocity) > 0.3 ? (velocity > 0 ? -1 : +1) : 0; // signo invertido por scrollLeft
      const idx = getNearestIndex() + boost;
      scrollToIndex(idx);

      // reanudar autoplay si aplica
      if (autoplayMs) startAutoplay();
    };

    track.addEventListener('pointerdown', onPointerDown, { passive:false });
    window.addEventListener('pointermove', onPointerMove, { passive:false });
    window.addEventListener('pointerup', onPointerUp, { passive:true });
    window.addEventListener('pointercancel', onPointerUp, { passive:true });
    window.addEventListener('pointerleave', onPointerUp, { passive:true });

    // ---- Pausas por hover/focus ----
    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', () => { if (!dragging) startAutoplay(); });
    root.addEventListener('focusin', stopAutoplay);
    root.addEventListener('focusout', () => { if (!dragging) startAutoplay(); });

    // Navegación con wheel (shift o trackpads horizontales)
    root.addEventListener('wheel', (e)=>{
      if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
        stopAutoplay();
        raf(()=> { track.scrollLeft += e.deltaX; });
        e.preventDefault();
        // Snap tras un pequeño idle
        clearTimeout(root._wmcWheelIdle);
        root._wmcWheelIdle = setTimeout(()=> scrollToIndex(getNearestIndex()), 120);
      }
    }, { passive:false });

    // Snap en resize (evita posiciones raras tras cambios de ancho)
    let resizeT = null;
    window.addEventListener('resize', ()=>{
      if (resizeT) clearTimeout(resizeT);
      resizeT = setTimeout(()=> scrollToIndex(getNearestIndex(), 'auto'), 80);
    });

    // Inicio
    startAutoplay();
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.wmc-tp-carousel').forEach(initCarousel);
  });
})();
