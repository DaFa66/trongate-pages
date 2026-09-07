<script>
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
