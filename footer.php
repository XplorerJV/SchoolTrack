</div><!-- end page-content -->
</div><!-- end main-content -->

<script>
feather.replace();

// Show hamburger on mobile
function checkMobile(){
    var btn = document.getElementById('menuToggle');
    if(btn) btn.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
}
checkMobile();
window.addEventListener('resize', checkMobile);

function toggleSidebar(){
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebarOverlay');
    s.classList.toggle('open');
    o.classList.toggle('open');
}
document.getElementById('sidebarOverlay')?.addEventListener('click', toggleSidebar);

// Auto dismiss alerts
document.querySelectorAll('.alert').forEach(a=>{
    setTimeout(()=>{ a.style.opacity='0'; a.style.transition='opacity .5s'; setTimeout(()=>a.remove(),500); }, 4000);
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn=>{
    btn.addEventListener('click', e=>{
        if(!confirm(btn.dataset.confirm)) e.preventDefault();
    });
});
</script>
</body>
</html>