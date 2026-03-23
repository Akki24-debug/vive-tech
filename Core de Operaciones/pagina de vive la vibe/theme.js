/* theme.js - aplica temas declarados en VIVE_RESOURCES.pageThemes */
(function(){
  function applyTheme(key){
    const resources = window.VIVE_RESOURCES || {};
    const themes = resources.pageThemes || {};
    const selected = themes[key] || themes.default || {};
    const root = document.documentElement;
    Object.entries(selected).forEach(([cssVar, value])=>{
      try{ root.style.setProperty(cssVar, value); }catch(e){ /* ignore */ }
    });
  }

  window.VIVE_APPLY_THEME = applyTheme;
})();

