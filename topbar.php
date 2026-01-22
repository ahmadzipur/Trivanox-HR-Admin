<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user']['id'])) {

    $query_count = "SELECT COUNT(*) AS total 
                    FROM notifications 
                    WHERE target_id = ? AND status = 'unread'";

    $stmt = $conn->prepare($query_count);
    $stmt->bind_param("i", $_SESSION['user']['id']);
    $stmt->execute();
    $stmt->bind_result($total_notifications);
    $stmt->fetch();
    $stmt->close();

    // SIMPAN KE SESSION
    $_SESSION['user']['jumlah_notifications'] = (int) $total_notifications;
}
?>
<!--Start topbar header-->
<header class="topbar-nav">
    <nav class="navbar navbar-expand fixed-top">
        <ul class="navbar-nav mr-auto align-items-center">
            <li class="nav-item">
                <a class="nav-link toggle-menu" href="javascript:void();">
                    <i class="icon-menu menu-icon"></i>
                </a>
            </li>
             <!--<li class="nav-item">
                <form class="search-bar">
                    <input type="text" class="form-control" placeholder="Enter keywords">
                    <a href="javascript:void();"><i class="icon-magnifier"></i></a>
                </form> 
            </li>-->
        </ul>

        <ul class="navbar-nav align-items-center right-nav-link">
            <!--
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle dropdown-toggle-nocaret waves-effect" data-toggle="dropdown" href="javascript:void();">
                    <i class="fa fa-envelope-o"></i>
                        <span class="badge badge-danger badge-up">3</span></a>
                
                <ul class="dropdown-menu dropdown-menu-right">
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item">Belum ada pesan</li>
                </ul>
            </li>
            -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle dropdown-toggle-nocaret waves-effect"
                   data-toggle="dropdown"
                   href="javascript:void();"><i class="fa fa-bell-o"></i>
                   <?php if($_SESSION["user"]["jumlah_notifications"] > 0): ?>
                        <span class="badge badge-danger badge-up"><?= $_SESSION["user"]["jumlah_notifications"] ?></span></a>
                   <?php else:; ?>
                        <span class="badge badge-danger badge-up"></span></a>
                   <?php endif; ?>
            
                <ul class="dropdown-menu dropdown-menu-right">
                    <?php 
                    // Cek apakah data notifikasi ada di session
                    if (isset($_SESSION['notifications']) && !empty($_SESSION['notifications'])) {
                        foreach ($_SESSION['notifications'] as $row_notifications) {
                            
                            echo "
                            <div class='mx-3 mt-0 mb-1' style='width:250px'>
                                <a href='" . $row_notifications['target_page'] . "'>
                                <i class='" . $row_notifications['icon'] . "'></i> <strong>" . $row_notifications['title'] . "</strong><br>
                                <small>" . $row_notifications['message'] . "</small></a>
                            </div>
                            <li class='dropdown-divider'></li>
                            ";

                            //echo "ID: " . $row_notifications['id_notification'] . "<br>";
                                                        //echo "Target Page: " . $row_notifications['target_page'] . "<br>";
                            //echo "Created At: " . $row_notifications['created_at'] . "<br>";
                            //echo "Status: " . $row_notifications['status'] . "<br><br>";
                        }
                    } else {
                        echo "
                    <li class='dropdown-divider'></li>
                    <li class='dropdown-item'>Belum ada notifikasi</li>";
                    }
                    ?>
                </ul>
            </li>
            <!--
            <li class="nav-item language">
                <a class="nav-link dropdown-toggle dropdown-toggle-nocaret waves-effect" data-toggle="dropdown" href="javascript:void();"><i class="fa fa-flag"></i></a>
                <ul class="dropdown-menu dropdown-menu-right">
                    <li class="dropdown-item"> <i class="flag-icon flag-icon-gb mr-2"></i> English</li>
                    <li class="dropdown-item"> <i class="flag-icon flag-icon-fr mr-2"></i> French</li>
                    <li class="dropdown-item"> <i class="flag-icon flag-icon-cn mr-2"></i> Chinese</li>
                    <li class="dropdown-item"> <i class="flag-icon flag-icon-de mr-2"></i> German</li>
                </ul>
            </li>
            -->
            <li class="nav-item">
                <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" data-toggle="dropdown" href="#">
                    <span class="user-profile"><img src="<?php echo htmlspecialchars($sesi_user["foto_profile"]); ?>" class="img-circle" alt="user avatar"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-right">
                    <li class="dropdown-item user-details">
                        <a href="javaScript:void();">
                            <div class="media">
                                <div class="avatar"><img class="align-self-start mr-3" src="<?php echo htmlspecialchars($sesi_user["foto_profile"]); ?>" alt="user avatar"></div>
                                <div class="media-body">
                                    <h6 class="mt-2 user-title"><?php echo htmlspecialchars($sesi_user["name"]); ?></h6>
                                    <p class="user-subtitle"><?php echo htmlspecialchars($sesi_user["email"]); ?></p>
                                </div>
                            </div>
                        </a>
                    </li>
                    <!--
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><i class="icon-envelope mr-2"></i> Inbox</li>
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><i class="icon-wallet mr-2"></i> Account</li>
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><i class="icon-settings mr-2"></i> Setting</li>
                    -->
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><a href='data-absensi-saya'><i class="zmdi zmdi-calendar-check"></i> Data Absensi Saya</a></li>
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><a href='data-izin-cuti-saya'><i class="zmdi zmdi-time-restore"></i> Data Izin, Cuti & Sakit Saya</a></li>
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><a href='profile'><i class="zmdi zmdi-account"></i>   Profil</a></li>
                    <li class="dropdown-divider"></li>
                    <li class="dropdown-item"><a href='logout'><i class="zmdi zmdi-power"></i>   Logout</a></li>
                </ul>
            </li>
        </ul>
    </nav>
</header>
<!--End topbar header-->