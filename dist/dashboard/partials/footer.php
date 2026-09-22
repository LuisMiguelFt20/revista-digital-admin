</div></main>
<script src="../assets/js/core/libs.min.js"></script>
<script src="../assets/js/core/external.min.js"></script>
<script src="../assets/js/hope-ui.js" defer></script>
<script>
const root=document.documentElement,boton=document.getElementById('modo');
function aplicarModo(){const oscuro=localStorage.getItem('modoRevista')==='oscuro';root.classList.toggle('dark',oscuro);boton.textContent=oscuro?'☀️ Claro':'🌙 Oscuro'}
boton?.addEventListener('click',()=>{localStorage.setItem('modoRevista',root.classList.contains('dark')?'claro':'oscuro');aplicarModo()});aplicarModo();
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay'),abrirMenu=document.getElementById('abrirMenu');
function cerrarMenu(){sidebar?.classList.remove('open');overlay?.classList.remove('show');abrirMenu?.setAttribute('aria-expanded','false')}
abrirMenu?.addEventListener('click',()=>{const abierto=sidebar?.classList.toggle('open');overlay?.classList.toggle('show',abierto);abrirMenu.setAttribute('aria-expanded',abierto?'true':'false')});
overlay?.addEventListener('click',cerrarMenu);document.addEventListener('keydown',e=>{if(e.key==='Escape')cerrarMenu()});sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',cerrarMenu));
document.getElementById('busquedaGlobal')?.addEventListener('input',function(){const texto=this.value.toLowerCase().trim();document.querySelectorAll('[data-fila]').forEach(fila=>fila.style.display=fila.innerText.toLowerCase().includes(texto)?'':'none')});
</script></body></html>
