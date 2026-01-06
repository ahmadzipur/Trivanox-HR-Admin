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

        <?php if ($sesi_user["role"] === 'staff' || $sesi_user["role"] === 'admin'): ?>
            <li>
                <a href="absensi">
                    <i class="zmdi zmdi-time"></i> <span>Absensi</span>
                </a>
            </li>
            <li>
                <a href="company">
                    <i class="zmdi zmdi-store"></i> <span>Perusahaan</span>
                </a>
            </li>

            <li>
                <a href="branch">
                    <i class="zmdi zmdi-city"></i> <span>Cabang</span>
                </a>
            </li>

            <li>
                <a href="divisi">
                    <i class="zmdi zmdi-collection-text"></i> <span>Divisi</span>
                </a>
            </li>

            <li>
                <a href="karyawan">
                    <i class="zmdi zmdi-face"></i> <span>Karyawan</span>
                </a>
            </li>
            <li>
                <a href="data-absensi">
                    <i class="zmdi zmdi-calendar"></i> <span>Data Absensi Karyawan</span>
                </a>
            </li>
            <li>
                <a href="data-izin-cuti">
                    <i class="zmdi zmdi-assignment-check"></i> <span>Data Izin, Cuti & Sakit</span>
                </a>
            </li>
        <?php endif; ?>
        <li>
            <a href="data-absensi-saya">
                <i class="zmdi zmdi-calendar"></i> <span>Data Absensi Saya</span>
            </a>
        </li>
        <li>
            <a href="izin-cuti">
                <i class="zmdi zmdi-check-circle"></i> <span>Pengajuan Izin, Cuti & Sakit</span>
            </a>
        <li>
            <a href="profile">
                <i class="zmdi zmdi-account"></i> <span>Profile</span>
            </a>
        </li>
        <li>
            <a href="notifikasi">
                <i class="zmdi zmdi-notifications"></i> <span>Notifikasi</span>
            </a>
        </li>

    </ul>

</div>
<!--End sidebar-wrapper-->