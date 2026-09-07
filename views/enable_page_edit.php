<script>
  // Font Awesome 4.7 (bundled locally) - the editor controls use fa glyphs.
  const faStyleSheet = document.createElement('link');
  faStyleSheet.rel = 'stylesheet';
  faStyleSheet.href = '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/css/font-awesome.min.css';
  document.head.appendChild(faStyleSheet);

  const trongatePagesObj = {
    baseUrl: '<?= BASE_URL ?>',
    trongatePagesId: '<?= $record_id ?>',
    trongatePagesToken: '<?= $csrf_token ?>',
    currentImgDir: '',
    inviteClearHome: 0,
    pageBody: document.getElementsByTagName('body')[0],
    defaultActiveElParent: document.getElementsByClassName('page-content')[0],
    headlineTags: ["H1", "H2", "H3", "H4", "H5", "h1", "h2", "h3", "h4", "h5"],
    targetTable: 'pages',
    imgUploadApi: '<?= $img_upload_api ?>',
    moduleAssetsTrigger: '<?= MODULE_ASSETS_TRIGGER ?>',
    activeElParent: document.getElementsByClassName('page-content')[0],
    activeEl: document.getElementsByClassName('page-content')[0],
    currentlySelectedElType: '',
    targetNewElLocation: '',
    editorDock: {},
    lastHeadlineSelected: {},
    storedRange: null,
    targetVideoDiv: null,
    textDivSampleText: '<?= $sample_text ?>'
  };

  const tgpScriptUrls = [
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/button_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/camera_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/code_view.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/divider_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/dock_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/element_adder_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/folder_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/headline_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/image_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/text_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/trongate_pages.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/toolbar_manager.js',
    '<?= BASE_URL ?>pages<?= MODULE_ASSETS_TRIGGER ?>/js/youtube_manager.js'
  ];

  const tgpModals = [
    'tgp-button-modal',
    'tgp-camera-modal',
    'tgp-code-view-modal',
    'tgp-conf-trashify-modal',
    'tgp-confirm-save-page',
    'tgp-create-page-el',
    'tgp-delete-page-modal',
    'tgp-image-modal',
    'tgp-intercept-add-el',
    'tgp-link-modal',
    'tgp-media-manager',
    'tgp-modal',
    'tgp-mobi-options',
    'tgp-settings-modal',
    'tgp-video-overlay',
    'tgp-youtube-modal'
  ];

  // Modal helpers (ported from the v1 app.js so the module is self-contained
  // and does not depend on any application-level JavaScript).
  const body = document.getElementsByTagName('body')[0];
  function _(elementId) {
    return document.getElementById(elementId);
  }

  function openModal(modalId) {
    var pageOverlay = document.getElementById('overlay');

    if (typeof pageOverlay == 'undefined' || pageOverlay == null) {
      var modalContainer = document.createElement('div');
      modalContainer.setAttribute('id', 'modal-container');
      modalContainer.setAttribute('style', 'z-index: 3;');
      body.prepend(modalContainer);

      var overlay = document.createElement('div');
      overlay.setAttribute('id', 'overlay');
      overlay.setAttribute('style', 'z-index: 2');
      body.prepend(overlay);

      var targetModal = _(modalId);
      targetModalContent = targetModal.innerHTML;
      targetModal.remove();

      var newModal = document.createElement('div');
      newModal.setAttribute('class', 'modal');
      newModal.setAttribute('id', modalId);
      newModal.style.zIndex = 4;
      newModal.innerHTML = targetModalContent;
      modalContainer.appendChild(newModal);

      setTimeout(() => {
        newModal.style.opacity = 1;
        newModal.style.marginTop = '12vh';
      }, 0);
    }
  }

  function closeModal() {
    var modalContainer = document.getElementById('modal-container');
    if (modalContainer) {
      var openModal = modalContainer.firstChild;
      openModal.style.zIndex = -4;
      openModal.style.opacity = 0;
      openModal.style.marginTop = '12vh';
      openModal.style.display = 'none';
      document.body.appendChild(openModal);
      modalContainer.remove();

      var overlay = document.getElementById('overlay');
      if (overlay) {
        overlay.remove();
      }
    }
  }

  function tgpLoadScripts(urls) {
    const promises = urls.map(url => {
      return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(`Failed to load script: ${url}`);
        script.src = url;
        document.head.appendChild(script);
      });
    });
    return Promise.all(promises);
  }

  window.addEventListener('load', () => {
    tgpLoadScripts(tgpScriptUrls)
      .then(() => {
        tgpStartPageEditor();
      })
      .catch(error => {
        console.error(error);
      });
  });
</script>
