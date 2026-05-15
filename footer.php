</div><!-- end page-content -->
</div><!-- end main-content -->

<script>
feather.replace();
// Mobile sidebar toggle
document.getElementById('menuToggle')?.addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('open');
});

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