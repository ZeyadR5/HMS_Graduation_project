<style>
/* 3D tower loader made by: csozi | Website: www.csozi.hu*/
#hms-global-loader {
  position: fixed;
  inset: 0;
  background-color: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(4px);
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 1;
  visibility: visible;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

#hms-global-loader.hidden-loader {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
}

.csozi-loader {
  scale: 1.5; /* Adjusted scale to not be overwhelmingly huge */
  height: 50px;
  width: 40px;
}

.csozi-box {
  position: relative;
  opacity: 0;
  left: 10px;
}

.csozi-side-left {
  position: absolute;
  background-color: #286cb5;
  width: 19px;
  height: 5px;
  transform: skew(0deg, -25deg);
  top: 14px;
  left: 10px;
}

.csozi-side-right {
  position: absolute;
  background-color: #2f85e0;
  width: 19px;
  height: 5px;
  transform: skew(0deg, 25deg);
  top: 14px;
  left: -9px;
}

.csozi-side-top {
  position: absolute;
  background-color: #5fa8f5;
  width: 20px;
  height: 20px;
  rotate: 45deg;
  transform: skew(-20deg, -20deg);
}

.csozi-box-1 { animation: from-left 4s infinite; }
.csozi-box-2 { animation: from-right 4s infinite; animation-delay: 1s; }
.csozi-box-3 { animation: from-left 4s infinite; animation-delay: 2s; }
.csozi-box-4 { animation: from-right 4s infinite; animation-delay: 3s; }

@keyframes from-left {
  0% { z-index: 20; opacity: 0; translate: -20px -6px; }
  20% { z-index: 10; opacity: 1; translate: 0px 0px; }
  40% { z-index: 9; translate: 0px 4px; }
  60% { z-index: 8; translate: 0px 8px; }
  80% { z-index: 7; opacity: 1; translate: 0px 12px; }
  100% { z-index: 5; translate: 0px 30px; opacity: 0; }
}

@keyframes from-right {
  0% { z-index: 20; opacity: 0; translate: 20px -6px; }
  20% { z-index: 10; opacity: 1; translate: 0px 0px; }
  40% { z-index: 9; translate: 0px 4px; }
  60% { z-index: 8; translate: 0px 8px; }
  80% { z-index: 7; opacity: 1; translate: 0px 12px; }
  100% { z-index: 5; translate: 0px 30px; opacity: 0; }
}
</style>

<div id="hms-global-loader">
  <div class="csozi-loader">
    <div class="csozi-box csozi-box-1">
      <div class="csozi-side-left"></div>
      <div class="csozi-side-right"></div>
      <div class="csozi-side-top"></div>
    </div>
    <div class="csozi-box csozi-box-2">
      <div class="csozi-side-left"></div>
      <div class="csozi-side-right"></div>
      <div class="csozi-side-top"></div>
    </div>
    <div class="csozi-box csozi-box-3">
      <div class="csozi-side-left"></div>
      <div class="csozi-side-right"></div>
      <div class="csozi-side-top"></div>
    </div>
    <div class="csozi-box csozi-box-4">
      <div class="csozi-side-left"></div>
      <div class="csozi-side-right"></div>
      <div class="csozi-side-top"></div>
    </div>
  </div>
</div>

<script>
  // Hide loader when page is fully loaded
  window.addEventListener('load', function() {
    const loader = document.getElementById('hms-global-loader');
    if (loader) loader.classList.add('hidden-loader');
  });

  // Show loader on page navigation
  window.addEventListener('beforeunload', function() {
    const loader = document.getElementById('hms-global-loader');
    if (loader) loader.classList.remove('hidden-loader');
  });

  // Show loader on form submits
  document.addEventListener('submit', function(e) {
    const form = e.target;
    // Don't show if the form opens in a new tab or is a search form that might be handled by ajax
    if (form && !form.hasAttribute('target')) {
        const loader = document.getElementById('hms-global-loader');
        if (loader) loader.classList.remove('hidden-loader');
    }
  });

  // Intercept fetch API to show loader for actions
  const originalFetch = window.fetch;
  window.fetch = async function(...args) {
      const url = args[0] && typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url ? args[0].url : '');
      const isBackgroundPoll = url.includes('api-poll-notifications.php');
      
      const loader = document.getElementById('hms-global-loader');
      
      if (loader && !isBackgroundPoll) {
          loader.classList.remove('hidden-loader');
      }
      
      try {
          const response = await originalFetch.apply(this, args);
          return response;
      } finally {
          if (loader && !isBackgroundPoll) {
              loader.classList.add('hidden-loader');
          }
      }
  };
</script>
