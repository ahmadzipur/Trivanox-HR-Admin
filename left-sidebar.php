<!--Start sidebar-wrapper-->
<div id="sidebar-wrapper" data-simplebar="" data-simplebar-auto-hide="true">
    <div class="brand-logo">
        <a href="index">
            <img src="assets/images/logo-trivanox-256.webp" class="logo-icon" alt="logo icon">
            <h5 class="logo-text">HR Admin</h5>
        </a>
    </div>
    <ul class="sidebar-menu do-nicescrol">
        <li class="sidebar-header">MAIN NAVIGATION</li>
        <li>
            <a href="index">
                <i class="zmdi zmdi-view-dashboard"></i> <span>Dashboard</span>
            </a>
        </li>
        
          <?php if ($sesi_user["role"]==='staff' || $sesi_user["role"]==='admin' ): ?>
            <?php if ($sesi_user["role"]==='staff'): ?>

        <li>
            <a href="company">
                <i class="zmdi zmdi-store"></i> <span>Perusahaan</span>
            </a>
        </li>
            
          <?php endif; ?>

        <li>
            <a href="karyawan">
                <i class="zmdi zmdi-face"></i> <span>Karyawan</span>
            </a>
        </li>
        <li>
            <a href="icons.html">
                
<i class="zmdi zmdi-calendar-check"></i> <span>Cuti</span>
            </a>
        </li>

        <li>
            <a href="forms.html">
                
<i class="zmdi zmdi-assignment-check"></i> <span>Izin</span>
            </a>
        </li>

          <?php endif; ?>

    </ul>

</div>
<!--End sidebar-wrapper-->