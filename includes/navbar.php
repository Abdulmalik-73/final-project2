<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-hotel text-gold"></i> Ras <span class="text-gold">Hotel</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Services
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                        <li><a class="dropdown-item active" href="rooms.php" style="background-color: #f7931e; color: white;"><i class="fas fa-bed me-2"></i> All Rooms</a></li>
                        <li><a class="dropdown-item" href="services.php#restaurant"><i class="fas fa-utensils me-2"></i> Restaurant & Dining</a></li>
                        <li><a class="dropdown-item" href="services.php#ethiopian-cuisine"><i class="fas fa-pepper-hot me-2"></i> Traditional Ethiopian Cuisine</a></li>
                        <li><a class="dropdown-item" href="services.php#international-buffet"><i class="fas fa-globe me-2"></i> International Buffet</a></li>
                        <li><a class="dropdown-item" href="services.php#spa"><i class="fas fa-spa me-2"></i> Spa & Wellness</a></li>
                        <li><a class="dropdown-item" href="services.php#laundry"><i class="fas fa-tshirt me-2"></i> Laundry Services</a></li>
                        <li><a class="dropdown-item" href="services.php#amenities"><i class="fas fa-concierge-bell me-2"></i> Hotel Amenities</a></li>
                    </ul>
                </li>
                <?php if (!is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <?php if (in_array($_SESSION['user_role'], ['customer', 'guest'])): ?>
                    <li class="nav-item dropdown">
                        <a class="btn btn-gold btn-sm ms-2 dropdown-toggle" href="#" id="bookingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar-check"></i> Book Now
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="bookingDropdown">
                            <li><a class="dropdown-item" href="booking.php"><i class="fas fa-bed"></i> Book Room</a></li>
                            <li><a class="dropdown-item" href="food-booking.php"><i class="fas fa-utensils"></i> Order Food</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="btn btn-gold btn-sm ms-2 dropdown-toggle" href="#" id="bookingDropdownGuest" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Create account or login to book">
                            <i class="fas fa-calendar-check"></i> Book Now
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="bookingDropdownGuest">
                            <li><a class="dropdown-item" href="booking.php"><i class="fas fa-bed"></i> Book Room</a></li>
                            <li><a class="dropdown-item" href="food-booking.php"><i class="fas fa-utensils"></i> Order Food</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
                
                <!-- Shopping Cart -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="cart.php" id="cartLink">
                        <i class="fas fa-shopping-cart"></i> Cart
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-gold cart-badge" style="display: none;">
                            0
                        </span>
                    </a>
                </li>
                <?php if (is_logged_in()): ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/admin.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Panel
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/manager.php">
                                <i class="fas fa-chart-line"></i> Manager Dashboard
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'receptionist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard/receptionist.php">
                                <i class="fas fa-concierge-bell"></i> Reception
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'manager', 'receptionist'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="payment-verification.php">
                            <i class="fas fa-shield-alt"></i> Payment Verification
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (!in_array($_SESSION['user_role'], ['customer', 'guest'])): ?>
                    <!-- User Account Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="userAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-profile-icon">
                                <i class="fas fa-user-circle fa-2x text-gold"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown" aria-labelledby="userAccountDropdown">
                            <!-- User Info Header -->
                            <li class="dropdown-header" style="padding: 0.5rem 0.8rem;">
                                <div class="text-center">
                                    <i class="fas fa-user-circle text-gold mb-2"></i>
                                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    <span class="badge bg-gold mt-1"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Profile Section -->
                            <li class="dropdown-header text-muted small fw-bold">
                                <i class="fas fa-user me-1"></i> PROFILE
                            </li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2 text-primary"></i> View Profile</a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=photo"><i class="fas fa-camera me-2 text-info"></i> Change Photo</a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=info"><i class="fas fa-edit me-2 text-success"></i> Update Information</a></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Settings Section -->
                            <li class="dropdown-header text-muted small fw-bold">
                                <i class="fas fa-cog me-1"></i> SETTINGS
                            </li>
                            <li><a class="dropdown-item" href="settings.php?tab=password"><i class="fas fa-key me-2 text-warning"></i> Change Password</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=notifications"><i class="fas fa-bell me-2 text-info"></i> Notifications</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=privacy"><i class="fas fa-shield-alt me-2 text-primary"></i> Privacy Settings</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <!-- User Account Dropdown for Customers -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle p-0" href="#" id="userAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-profile-icon">
                                <i class="fas fa-user-circle fa-2x text-gold"></i>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown" aria-labelledby="userAccountDropdown">
                            <!-- User Info Header -->
                            <li class="dropdown-header" style="padding: 0.5rem 0.8rem;">
                                <div class="text-center">
                                    <i class="fas fa-user-circle text-gold mb-2"></i>
                                    <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    <span class="badge bg-gold mt-1"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Profile Section -->
                            <li class="dropdown-header text-muted small fw-bold">
                                <i class="fas fa-user me-1"></i> PROFILE
                            </li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2 text-primary"></i> View Profile</a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=photo"><i class="fas fa-camera me-2 text-info"></i> Change Photo</a></li>
                            <li><a class="dropdown-item" href="profile.php?tab=info"><i class="fas fa-edit me-2 text-success"></i> Update Information</a></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Settings Section -->
                            <li class="dropdown-header text-muted small fw-bold">
                                <i class="fas fa-cog me-1"></i> SETTINGS
                            </li>
                            <li><a class="dropdown-item" href="settings.php?tab=password"><i class="fas fa-key me-2 text-warning"></i> Change Password</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=notifications"><i class="fas fa-bell me-2 text-info"></i> Notifications</a></li>
                            <li><a class="dropdown-item" href="settings.php?tab=privacy"><i class="fas fa-shield-alt me-2 text-primary"></i> Privacy Settings</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                        </ul>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
