// Product gallery: thumbnails swap the main stage between photos and video.
// Hover zoom is enabled for photos only.
(function () {
  var root = document.querySelector('[data-gallery]');
  if (!root) return;
  var stage = root.querySelector('.stage');
  var thumbs = Array.prototype.slice.call(root.querySelectorAll('.thumb'));
  if (!stage || !thumbs.length) return;

  var zoomFactor = 2.5;
  var currentImgSrc = '';
  var currentImgEl = null;
  var isVideoPlaying = false;

  // Zoom preview container
  var zoomPreview = root.querySelector('.stage-zoom-preview');
  if (!zoomPreview) {
    zoomPreview = document.createElement('div');
    zoomPreview.className = 'stage-zoom-preview';
    zoomPreview.setAttribute('aria-hidden', 'true');
    root.appendChild(zoomPreview);
  }

  // Aiming lens
  var lens = root.querySelector('.stage-zoom-lens');
  if (!lens) {
    lens = document.createElement('div');
    lens.className = 'stage-zoom-lens';
    lens.setAttribute('aria-hidden', 'true');
  }

  function getRenderedImageBox(imgEl, stageW, stageH) {
    var natW = (imgEl && imgEl.naturalWidth) ? imgEl.naturalWidth : stageW;
    var natH = (imgEl && imgEl.naturalHeight) ? imgEl.naturalHeight : stageH;
    var stageRatio = stageW / stageH;
    var imgRatio = natW / natH;

    var renderW, renderH, offsetX, offsetY;
    if (imgRatio > stageRatio) {
      renderW = stageW;
      renderH = stageW / imgRatio;
      offsetX = 0;
      offsetY = (stageH - renderH) / 2;
    } else {
      renderH = stageH;
      renderW = stageH * imgRatio;
      offsetX = (stageW - renderW) / 2;
      offsetY = 0;
    }
    return { width: renderW, height: renderH, left: offsetX, top: offsetY };
  }

  function onMouseEnter(e) {
    if (isVideoPlaying || !currentImgSrc) return;

    var stageRect = stage.getBoundingClientRect();
    var galleryRect = root.getBoundingClientRect();
    var spaceOnRight = document.documentElement.clientWidth - stageRect.right;

    // If there is enough room on the right (at least 280px), use Flyout Zoom
    if (spaceOnRight >= 280 && window.innerWidth > 800) {
      var previewW = Math.min(stageRect.width, spaceOnRight - 24);
      var previewH = stageRect.height;
      var leftPos = stageRect.right - galleryRect.left + 16;
      var topPos = stageRect.top - galleryRect.top;

      zoomPreview.style.top = topPos + 'px';
      zoomPreview.style.left = leftPos + 'px';
      zoomPreview.style.width = previewW + 'px';
      zoomPreview.style.height = previewH + 'px';
      zoomPreview.style.backgroundImage = 'url("' + currentImgSrc + '")';
      zoomPreview.style.display = 'block';

      if (lens.parentNode !== stage) {
        stage.appendChild(lens);
      }
      lens.style.display = 'block';
    } else {
      // Otherwise, use sleek In-Place Inner Zoom directly inside the stage
      zoomPreview.style.display = 'none';
      lens.style.display = 'none';
      if (currentImgEl) {
        currentImgEl.style.transform = 'scale(2.2)';
      }
    }

    onMouseMove(e);
  }

  function onMouseMove(e) {
    if (isVideoPlaying || !currentImgSrc) return;

    var stageRect = stage.getBoundingClientRect();
    var mouseX = e.clientX - stageRect.left;
    var mouseY = e.clientY - stageRect.top;

    // Normalized coordinates across the stage (0% to 100%)
    var normX = Math.max(0, Math.min(1, mouseX / stageRect.width));
    var normY = Math.max(0, Math.min(1, mouseY / stageRect.height));

    // Handle Flyout Zoom
    if (zoomPreview.style.display === 'block') {
      var imgBox = getRenderedImageBox(currentImgEl, stageRect.width, stageRect.height);
      var previewW = zoomPreview.offsetWidth || stageRect.width;
      var previewH = zoomPreview.offsetHeight || stageRect.height;

      var lensW = Math.max(40, Math.min(imgBox.width, previewW / zoomFactor));
      var lensH = Math.max(40, Math.min(imgBox.height, previewH / zoomFactor));

      lens.style.width = lensW + 'px';
      lens.style.height = lensH + 'px';

      var lensX = mouseX - (lensW / 2);
      var lensY = mouseY - (lensH / 2);

      var minX = imgBox.left;
      var maxX = imgBox.left + imgBox.width - lensW;
      var minY = imgBox.top;
      var maxY = imgBox.top + imgBox.height - lensH;

      lensX = Math.max(minX, Math.min(lensX, maxX));
      lensY = Math.max(minY, Math.min(lensY, maxY));

      lens.style.transform = 'translate3d(' + lensX + 'px, ' + lensY + 'px, 0)';

      var bgW = imgBox.width * zoomFactor;
      var bgH = imgBox.height * zoomFactor;
      zoomPreview.style.backgroundSize = bgW + 'px ' + bgH + 'px';

      var spanX = maxX - minX;
      var spanY = maxY - minY;
      var bgPosX = spanX > 0 ? ((lensX - minX) / spanX) * 100 : 0;
      var bgPosY = spanY > 0 ? ((lensY - minY) / spanY) * 100 : 0;

      zoomPreview.style.backgroundPosition = bgPosX + '% ' + bgPosY + '%';
    } else if (currentImgEl) {
      // In-Place Zoom tracking
      currentImgEl.style.transformOrigin = (normX * 100) + '% ' + (normY * 100) + '%';
    }
  }

  function onMouseLeave() {
    lens.style.display = 'none';
    zoomPreview.style.display = 'none';
    if (currentImgEl) {
      currentImgEl.style.transform = 'scale(1)';
      currentImgEl.style.transformOrigin = 'center center';
    }
  }

  stage.addEventListener('mouseenter', onMouseEnter);
  stage.addEventListener('mousemove', onMouseMove);
  stage.addEventListener('mouseleave', onMouseLeave);

  window.addEventListener('resize', onMouseLeave);

  function show(thumb, autoplay) {
    onMouseLeave();
    isVideoPlaying = false;

    thumbs.forEach(function (t) {
      t.classList.toggle('is-active', t === thumb);
      t.setAttribute('aria-pressed', t === thumb ? 'true' : 'false');
    });
    stage.replaceChildren();

    if (thumb.dataset.type === 'video') {
      stage.classList.remove('has-zoom');
      currentImgSrc = '';

      var video = document.createElement('video');
      video.controls = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.src = thumb.dataset.src;
      if (thumb.dataset.poster) {
        video.poster = thumb.dataset.poster;
      }
      stage.appendChild(video);
      currentImgEl = video;

      var play = document.createElement('button');
      play.type = 'button';
      play.className = 'stage-play';
      play.setAttribute('aria-label', 'Play video');

      function startVideoPlayback() {
        isVideoPlaying = true;
        onMouseLeave();
        stage.classList.remove('has-zoom');
        if (play.parentNode) play.remove();
        video.play();
      }

      play.addEventListener('click', startVideoPlayback);
      video.addEventListener('play', function () {
        isVideoPlaying = true;
        onMouseLeave();
        stage.classList.remove('has-zoom');
        if (play.parentNode) play.remove();
      });

      video.addEventListener('pause', function () {
        isVideoPlaying = false;
        stage.classList.remove('has-zoom');
      });

      stage.appendChild(play);

      if (autoplay) {
        startVideoPlayback();
      }
    } else {
      stage.classList.add('has-zoom');
      currentImgSrc = thumb.dataset.src;

      var img = document.createElement('img');
      img.src = thumb.dataset.src;
      img.alt = 'Product photo';
      currentImgEl = img;
      stage.appendChild(img);
    }
  }

  thumbs.forEach(function (t) {
    t.addEventListener('click', function () { show(t, true); });
  });

  show(thumbs[0], false);
})();
