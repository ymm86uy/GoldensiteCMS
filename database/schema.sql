-- Canonical schema. Existing installations use SchemaMigrator for additive updates.

CREATE TABLE IF NOT EXISTS plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    folder VARCHAR(100) NOT NULL UNIQUE,
    active TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_waf_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    username VARCHAR(100) DEFAULT 'N/A',
    reason VARCHAR(255) NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_password_resets (
    user_id INT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    account_stamp CHAR(64) NOT NULL,
    expires_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_ads_clicks (
    campaign_id CHAR(16) NOT NULL,
    page_path VARCHAR(200) NOT NULL,
    clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (campaign_id,page_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS active_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    role_id INT DEFAULT 2,
    last_url VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS firewall_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                country_code VARCHAR(10) DEFAULT 'XX',
                event_type VARCHAR(50) NOT NULL,
                request_method VARCHAR(10) NOT NULL,
                request_uri VARCHAR(255) NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS firewall_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(255) NOT NULL,
                rule_type VARCHAR(20) DEFAULT 'block',
                description VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY target_rule (ip_address, rule_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    filesize INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    version VARCHAR(20) DEFAULT '1.0.0',
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_404_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                hits INT DEFAULT 1,
                last_detected DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_active_modules (
            module_id VARCHAR(100) PRIMARY KEY,
            is_active TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                username VARCHAR(100) DEFAULT 'Sistema',
                action VARCHAR(100) NOT NULL,
                description TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT '',
                user_agent VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_analytics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    page_url VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_analytics_hits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_url VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_appointments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_name VARCHAR(150) NOT NULL,
                    client_phone VARCHAR(50) NOT NULL,
                    service_requested VARCHAR(150) NOT NULL,
                    booking_date DATETIME NOT NULL,
                    account_id INT NULL,
                    availability_id INT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_booking_professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_booking_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    service VARCHAR(150) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    appointment_id INT NULL UNIQUE,
    closed TINYINT NOT NULL DEFAULT 0,
    INDEX booking_professional_time (professional_id,starts_at,ends_at),
    INDEX booking_availability_time (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    venue VARCHAR(150) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    capacity INT NOT NULL,
    active TINYINT NOT NULL DEFAULT 1,
    INDEX events_date (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_event_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    code_hash CHAR(64) NOT NULL UNIQUE,
    sealed_code VARCHAR(255) NOT NULL,
    state VARCHAR(16) NOT NULL DEFAULT 'issued',
    checked_by INT NULL,
    checked_at DATETIME NULL,
    UNIQUE KEY event_attendee (event_id,user_id),
    INDEX event_capacity (event_id,state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_academy_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    published TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_academy_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    question VARCHAR(250) NULL,
    answers TEXT NULL,
    correct_index TINYINT NULL,
    INDEX academy_course_lessons (course_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_academy_enrollments (
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    certificate CHAR(48) NULL UNIQUE,
    certificate_name VARCHAR(150) NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (course_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_academy_progress (
    lesson_id INT NOT NULL,
    user_id INT NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lesson_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_s3_replicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    library VARCHAR(16) NOT NULL,
    resource_ref VARCHAR(255) NOT NULL,
    bucket VARCHAR(63) NOT NULL,
    region VARCHAR(40) NOT NULL,
    object_key VARCHAR(255) NOT NULL UNIQUE,
    sha256 CHAR(64) NOT NULL,
    bytes INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_automation_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                automation_name VARCHAR(150) NOT NULL,
                status VARCHAR(20) NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_automations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                trigger_event VARCHAR(100) NOT NULL,
                condition_field VARCHAR(100) NULL,
                condition_operator VARCHAR(50) DEFAULT 'equals',
                condition_value VARCHAR(100) NULL,
                action_type VARCHAR(100) NOT NULL,
                execution_delay INT DEFAULT 0,
                target_value VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_name VARCHAR(100) NOT NULL,
                user_role VARCHAR(20) DEFAULT 'user',
                message TEXT NOT NULL,
                channel VARCHAR(50) DEFAULT 'General-Chat',
                image_url VARCHAR(255) NULL,
                is_flagged TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_chat_rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                access_type ENUM('public', 'private', 'vip') DEFAULT 'public',
                moderator_username VARCHAR(100) DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_chat_sanctions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'mute',
                reason VARCHAR(255) NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_chat_user_roles (
                username VARCHAR(100) PRIMARY KEY,
                chat_role VARCHAR(20) NOT NULL DEFAULT 'member',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                author_name VARCHAR(100) NOT NULL,
                author_email VARCHAR(150) NOT NULL,
                comment_text TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'approved',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                card_holder VARCHAR(150) NOT NULL,
                brand VARCHAR(50) NOT NULL DEFAULT 'Visa',
                last4 VARCHAR(4) NOT NULL,
                exp_month VARCHAR(2) NOT NULL,
                exp_year VARCHAR(4) NOT NULL,
                payment_token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_gifts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 5.00,
                icon VARCHAR(50) DEFAULT '🎁',
                image_url TEXT DEFAULT NULL,
                status TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_likes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                target_id INT NOT NULL,
                type VARCHAR(10) DEFAULT 'like',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY user_target (user_id, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                message TEXT DEFAULT NULL,
                media_url TEXT DEFAULT NULL,
                media_type VARCHAR(20) DEFAULT NULL,
                gift_icon VARCHAR(50) DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                sender_id INT NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'like',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 9.99,
                badge VARCHAR(50) DEFAULT 'VIP',
                description TEXT,
                image_url TEXT DEFAULT NULL,
                status TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) DEFAULT '',
                birth_date DATE DEFAULT '2000-01-01',
                age INT NOT NULL DEFAULT 18,
                gender VARCHAR(50) NOT NULL DEFAULT 'Otro',
                looking_for VARCHAR(50) DEFAULT 'Todos',
                location_city VARCHAR(100) DEFAULT 'Montevideo',
                location_country VARCHAR(100) DEFAULT 'Uruguay',
                country_code VARCHAR(5) DEFAULT 'UY',
                hide_flag TINYINT(1) DEFAULT 0,
                interests TEXT,
                hobbies TEXT,
                bio TEXT,
                avatar_url TEXT,
                avatar_approved TINYINT(1) DEFAULT 1,
                compatibility_score INT DEFAULT 88,
                wallet_balance DECIMAL(10,2) DEFAULT 20.00,
                is_vip TINYINT(1) DEFAULT 0,
                vip_expires_at DATETIME DEFAULT NULL,
                boosted_until DATETIME DEFAULT NULL,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_stories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                media_url TEXT NOT NULL,
                media_type VARCHAR(20) DEFAULT 'image',
                caption VARCHAR(255) DEFAULT '',
                photo_id CHAR(32) NULL,
                is_hidden TINYINT(1) DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_downloads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                folder VARCHAR(80) NOT NULL DEFAULT 'General',
                version_label VARCHAR(32) NOT NULL DEFAULT '1.0',
                resource_id INT NULL,
                access_mode VARCHAR(20) NOT NULL DEFAULT 'public_link',
                assigned_user INT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                file_bytes BIGINT NULL,
                sha256 CHAR(64) NULL,
                mime_type VARCHAR(100) NULL,
                allow_legacy_links TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_galleries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            layout_type VARCHAR(50) DEFAULT 'masonry',
            animation_fx VARCHAR(50) DEFAULT 'zoom-fade',
            images_json LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_gdpr_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_email VARCHAR(150) NULL,
    customer_phone VARCHAR(50) NULL,
    plugin_id VARCHAR(100) NOT NULL,
    module_id VARCHAR(100) NULL,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    verification_token VARCHAR(255) NULL,
    custom_price VARCHAR(100) NULL,
    client_name VARCHAR(150) NULL,
    client_contact VARCHAR(150) NULL,
    client_phone VARCHAR(50) NULL,
    client_email VARCHAR(150) NULL,
    authorized_domain VARCHAR(150) NULL,
    domain_host VARCHAR(150) NULL,
    status VARCHAR(50) DEFAULT 'INACTIVE',
    notified_warning TINYINT(1) DEFAULT 0,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_license_activations (
    module_id VARCHAR(100) PRIMARY KEY,
    license_id CHAR(32) NOT NULL UNIQUE,
    signed_code TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_license_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    event VARCHAR(40) NOT NULL,
    reference_id VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_license_revocations (
    license_id CHAR(32) PRIMARY KEY,
    revoked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_login_studio (
            id INT PRIMARY KEY DEFAULT 1,
            active_style VARCHAR(50) DEFAULT 'glass_neon',
            bg_image_url TEXT NULL,
            company_logo_url TEXT NULL,
            company_name VARCHAR(150) DEFAULT 'GoldenSiteCMS',
            company_tagline VARCHAR(255) DEFAULT 'Acceso al Sistema',
            accent_color VARCHAR(20) DEFAULT '#38bdf8',
            enable_captcha TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_menus (
    page_id INT NULL,
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(150) NOT NULL,
                    url VARCHAR(255) NOT NULL,
                    icon VARCHAR(255) DEFAULT '',
                    badge_text VARCHAR(50) DEFAULT '',
                    badge_color VARCHAR(20) DEFAULT '#38bdf8',
                    is_target_blank TINYINT(1) DEFAULT 0,
                    visibility VARCHAR(20) DEFAULT 'all',
                    parent_id INT DEFAULT 0,
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_messages (
    inquiry_type VARCHAR(30) DEFAULT 'general',
    module_id VARCHAR(100) NULL,
    ticket_state VARCHAR(16) NOT NULL DEFAULT 'open',
    ticket_priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    ticket_assignee INT NULL,
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(150) NOT NULL,
                            email VARCHAR(150) NOT NULL,
                            subject VARCHAR(200) NOT NULL,
                            message TEXT NOT NULL,
                            status VARCHAR(20) DEFAULT 'unread',
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uri VARCHAR(255) NOT NULL,
                ip_hash VARCHAR(64) NOT NULL,
                visited_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_migration_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_platform VARCHAR(50) NOT NULL,
                items_imported INT DEFAULT 0,
                status VARCHAR(50) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_module_licenses (
                module_slug VARCHAR(100) PRIMARY KEY,
                license_key VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 0,
                activated_at DATETIME DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_module_prices (
            module_id VARCHAR(100) PRIMARY KEY,
            price_text VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_optimizer_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_pages (
    typography_json TEXT NULL,
    translations_json LONGTEXT NULL,
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            content LONGTEXT NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            og_image VARCHAR(255) NULL,
            status VARCHAR(20) DEFAULT 'published',
            is_published TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_posts (
    is_published TINYINT(1) DEFAULT 1,
    typography_json TEXT NULL,
    translations_json LONGTEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        slug VARCHAR(255) NOT NULL UNIQUE,
                        content LONGTEXT NULL,
                        excerpt TEXT NULL,
                        status VARCHAR(20) DEFAULT 'draft',
                        category VARCHAR(100) NULL,
                        source_platform VARCHAR(50) DEFAULT 'joomla',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                description TEXT,
                image_url VARCHAR(255),
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_redirects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_url VARCHAR(255) NOT NULL UNIQUE,
                target_url VARCHAR(255) NOT NULL,
                redirect_type INT DEFAULT 301,
                hits INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_sitemap_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                  consented_at DATETIME NULL,
                  consent_key CHAR(32) NULL,
                  optin_requested_at BIGINT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_system_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'UNREAD',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_vault_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(255) NOT NULL,
                description TEXT NULL,
                file_size VARCHAR(50) NULL,
                download_token VARCHAR(100) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_waf_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100) DEFAULT 'Invitado',
    user_agent VARCHAR(255) DEFAULT 'N/A',
    method VARCHAR(10) NOT NULL,
    target_url TEXT NOT NULL,
    vector VARCHAR(100) NOT NULL,
    risk_score INT DEFAULT 0,
    request_id CHAR(32) NULL,
    rule_ids VARCHAR(255) NULL,
    disposition VARCHAR(20) NOT NULL DEFAULT 'LEGACY',
    matched_fields VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_revoked_sessions (
    session_hash CHAR(64) PRIMARY KEY,
    revoked_by INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_webhook_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_name VARCHAR(150) NOT NULL,
                            customer_email VARCHAR(150) NOT NULL,
                            total DECIMAL(10,2) NOT NULL,
                            status VARCHAR(20) DEFAULT 'completed',
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    blocks_json LONGTEXT NULL,
    global_json LONGTEXT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    module VARCHAR(100) DEFAULT 'core'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content LONGTEXT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    author_id INT NULL,
    user_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        stock INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    totp_recovery_json TEXT NULL,
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    role_id INT NOT NULL DEFAULT 2,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    totp_secret VARCHAR(128) NULL,
    totp_last_counter BIGINT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_editorial_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity VARCHAR(8) NOT NULL,
    document_id INT NOT NULL,
    author_id INT NULL,
    author_name VARCHAR(100) NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    INDEX idx_editorial_document (entity,document_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_admin_settings (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_scim_users (
    id CHAR(32) PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    issuer_hash CHAR(64) NOT NULL,
    login_name VARCHAR(150) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    external_id VARCHAR(150) NULL,
    deleted TINYINT NOT NULL DEFAULT 0,
    UNIQUE KEY scim_login (issuer_hash,login_name),
    UNIQUE KEY scim_external (issuer_hash,external_id),
    INDEX scim_subject (issuer_hash,subject_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_saml_requests (
    relay_hash CHAR(64) PRIMARY KEY,
    browser_hash CHAR(64) NOT NULL,
    request_id VARCHAR(100) NOT NULL,
    config_hash CHAR(64) NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX saml_request_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_saml_replays (
    assertion_hash CHAR(64) PRIMARY KEY,
    expires_at BIGINT NOT NULL,
    INDEX saml_replay_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_catalog_prices (
  `module_id` varchar(150) NOT NULL,
  `price_value` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_blocks (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_chats (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_email` varchar(255) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_friends (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'ACCEPTED',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_matches (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_one` int(11) NOT NULL,
  `user_two` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_pages (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `content` longtext DEFAULT NULL,
  `in_menu` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_dating_reports (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `reported_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_marketplace_categories (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_marketplace_orders (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `buyer_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL,
  `platform_commission` decimal(10,2) NOT NULL,
  `vendor_earnings` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_marketplace_products (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` TEXT NULL,
  `category` varchar(100) DEFAULT 'General',
  `image_url` varchar(255) DEFAULT '',
  `slug` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','published','out_of_stock') DEFAULT 'published',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_marketplace_reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_token CHAR(32) NOT NULL UNIQUE,
  buyer_id INT NOT NULL,
  product_id INT NOT NULL,
  vendor_id INT NOT NULL,
  product_title VARCHAR(150) NOT NULL,
  store_name VARCHAR(100) NOT NULL,
  quantity INT NOT NULL,
  total_cents INT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX marketplace_buyer (buyer_id,id),
  INDEX marketplace_reservation_status (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_quarantine (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `original_path` varchar(255) NOT NULL,
  `quarantine_path` varchar(255) NOT NULL,
  `file_hash` varchar(64) NOT NULL,
  `risk_score` int(11) DEFAULT 0,
  `risk_level` varchar(20) DEFAULT 'SAFE',
  `reasons` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_user_payment_methods (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT 1,
  `card_holder` varchar(150) NOT NULL,
  `card_masked_number` varchar(20) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `expiry_date` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_vendor_payout_accounts (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `payout_method` varchar(50) NOT NULL,
  `account_details` varchar(255) NOT NULL,
  `balance_available` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS goldensite_vendors (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_access_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    permissions_json TEXT NOT NULL,
    networks_json TEXT NULL,
    require_mfa TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_access_members (
    user_id INT PRIMARY KEY,
    role_id INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_identity_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    issuer VARCHAR(255) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    UNIQUE KEY idx_external_identity (issuer, subject_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    schema_json LONGTEXT NOT NULL,
    success_message VARCHAR(500) NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_form_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    data_json LONGTEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_form_entries (form_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_deploy_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    checksum CHAR(64) NOT NULL,
    state VARCHAR(20) DEFAULT 'staging',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_message_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    body TEXT NOT NULL,
    delivery_state VARCHAR(20) DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_replies (message_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    actor_id INT NOT NULL,
    state VARCHAR(16) NOT NULL,
    priority VARCHAR(16) NOT NULL,
    assignee INT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ticket_history_message (message_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_builder_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    html_code LONGTEXT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_import_runs (
    token_hash CHAR(64) PRIMARY KEY,
    summary_json TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_webhook_deliveries (
    id CHAR(32) PRIMARY KEY,
    event_name VARCHAR(80) NOT NULL,
    payload_json TEXT NOT NULL,
    config_stamp CHAR(64) NOT NULL,
    state VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    http_code INT NULL,
    duration_ms INT NULL,
    last_error VARCHAR(255) NULL,
    next_attempt_at DATETIME NULL,
    claimed_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hooks_due (state,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_module_jobs (
 id CHAR(32) PRIMARY KEY,
 module_id VARCHAR(100) NOT NULL,
 kind VARCHAR(32) NOT NULL,
 dedupe_key CHAR(64) NOT NULL UNIQUE,
 group_key VARCHAR(100) NOT NULL DEFAULT '',
 payload MEDIUMTEXT NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'queued',
 run_at BIGINT NOT NULL,
 attempts INT NOT NULL DEFAULT 0,
 lease_token CHAR(32) NULL,
 claimed_at BIGINT NULL,
 finished_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_module_due (state,run_at),
 INDEX idx_module_group (group_key,state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_newsletter_campaigns (
 id INT AUTO_INCREMENT PRIMARY KEY,
 subject VARCHAR(200) NOT NULL,
 body MEDIUMTEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 send_at BIGINT NULL,
 queued_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_catalog_state (
 id INT PRIMARY KEY,
 revision BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_module_offers (
 module_id VARCHAR(100) PRIMARY KEY,
 tier VARCHAR(4) NOT NULL,
 amount_cents INT NOT NULL,
 currency CHAR(3) NOT NULL,
 period VARCHAR(5) NOT NULL,
 discount_percent INT NOT NULL DEFAULT 0,
 starts_at BIGINT NULL,
 ends_at BIGINT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_dating_accounts (
 user_id INT PRIMARY KEY,
 profile_id INT NOT NULL UNIQUE,
 birth_date DATE NULL,
 consent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 policy_version VARCHAR(16) NOT NULL DEFAULT '2026-11-04',
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 review_state VARCHAR(16) NOT NULL DEFAULT 'pending',
 moderation_hold TINYINT(1) NOT NULL DEFAULT 0,
 relationship_intent VARCHAR(20) NOT NULL DEFAULT 'relationship',
 email_verified_at DATETIME NULL,
 verified_email_hash CHAR(64) NULL,
 verify_nonce CHAR(32) NOT NULL,
 verify_requested_at BIGINT NOT NULL,
 min_age INT NOT NULL DEFAULT 18,
 max_age INT NOT NULL DEFAULT 100,
 hide_location TINYINT(1) NOT NULL DEFAULT 1,
 show_online TINYINT(1) NOT NULL DEFAULT 0,
 avatar_photo_id CHAR(32) NULL,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_dating_discovery (status,review_state,email_verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_dating_photos (
 id CHAR(32) PRIMARY KEY,
 profile_id INT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'pending',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_dating_album (profile_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_dating_relationships (
 id INT AUTO_INCREMENT PRIMARY KEY,
 low_profile INT NOT NULL,
 high_profile INT NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY dating_pair (low_profile,high_profile),
 INDEX idx_dating_match_high (high_profile,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_dating_privacy_blocks (
 blocker_id INT NOT NULL,
 target_id INT NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (blocker_id,target_id),
 INDEX idx_dating_reverse_block (target_id,blocker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_dating_safety_reports (
 id INT AUTO_INCREMENT PRIMARY KEY,
 reporter_id INT NOT NULL,
 target_id INT NOT NULL,
 reason VARCHAR(24) NOT NULL,
 detail TEXT NULL,
 message_id INT NULL,
 message_evidence TEXT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'open',
 moderator_note VARCHAR(1000) NULL,
 reviewed_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY dating_report_thread (reporter_id,target_id),
 INDEX idx_dating_reports_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_dating_adjustments (
 id CHAR(32) PRIMARY KEY,
 profile_id INT NOT NULL,
 reference VARCHAR(120) NOT NULL UNIQUE,
 amount DECIMAL(10,2) NOT NULL,
 reason VARCHAR(255) NOT NULL,
 actor_id INT NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_download_links (
 id CHAR(32) PRIMARY KEY,
 file_id INT NOT NULL,
 bound_user INT NULL,
 expires_at BIGINT NOT NULL,
 max_uses INT NOT NULL DEFAULT 0,
 uses INT NOT NULL DEFAULT 0,
 revoked_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_download_file_link (file_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_download_events (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 file_id INT NOT NULL,
 link_id CHAR(32) NULL,
 user_id INT NULL,
 result VARCHAR(20) NOT NULL,
 file_bytes BIGINT NOT NULL DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_download_event_file (file_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_joomla_categories (
 source_id INT UNSIGNED PRIMARY KEY, parent_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL,
 alias VARCHAR(191) NOT NULL, description TEXT NOT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL,
 INDEX siblings (parent_id,is_visible,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_entries (
 source_id INT UNSIGNED PRIMARY KEY, category_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL,
 alias VARCHAR(191) NOT NULL, description TEXT NOT NULL, version_name VARCHAR(255) NOT NULL,
 size_label VARCHAR(40) NOT NULL, downloads INT UNSIGNED NOT NULL, created_at DATETIME NULL,
 original_file VARCHAR(255) NOT NULL, preview_file VARCHAR(255) NOT NULL, position INT NOT NULL, is_visible TINYINT NOT NULL,
 INDEX category_recent (category_id,is_visible,created_at,source_id), INDEX recent (is_visible,created_at,source_id), INDEX source_original (original_file), INDEX source_preview (preview_file)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_menus (
 source_id INT UNSIGNED PRIMARY KEY, label VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL,
 component VARCHAR(64) NOT NULL, view_name VARCHAR(64) NOT NULL, target_id INT NOT NULL, position INT NOT NULL, shop TINYINT NOT NULL,
 INDEX menu_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_articles (
 source_id INT UNSIGNED PRIMARY KEY, slug VARCHAR(255) NOT NULL, category_id INT NOT NULL, category_title VARCHAR(255) NOT NULL,
 INDEX category_list (category_id,slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_tags (
 source_id INT UNSIGNED PRIMARY KEY, title VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_joomla_entry_tags (
 entry_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY (entry_id,tag_id),
 INDEX by_tag (tag_id,entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_catalog_media (
 entry_id INT UNSIGNED PRIMARY KEY, original_id INT UNSIGNED NULL, preview_id INT UNSIGNED NULL,
 view_mode ENUM('public','member','off') NOT NULL DEFAULT 'public',
 preview_mode ENUM('public','member','off') NOT NULL DEFAULT 'off',
 download_mode ENUM('public','member','off') NOT NULL DEFAULT 'off', INDEX original_file (original_id), INDEX preview_file (preview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_catalog_submissions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NOT NULL,
 author_id INT UNSIGNED NOT NULL, file_id INT UNSIGNED NOT NULL, title VARCHAR(200) NOT NULL,
 description TEXT NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX queue (status,id), INDEX author_queue (author_id,status), INDEX submission_file (file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS goldensite_community_profiles (
 user_id INT UNSIGNED PRIMARY KEY, display_name VARCHAR(80) NOT NULL,
 visibility ENUM('public','member','off') NOT NULL DEFAULT 'off',
 history_mode ENUM('public','member','off') NOT NULL DEFAULT 'off',
 fields_json TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_boards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    INDEX (category_id, id),
    FOREIGN KEY (category_id) REFERENCES goldensite_forum_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_topics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    board_id INT UNSIGNED NOT NULL,
    author_id INT NULL,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    kind VARCHAR(16) NOT NULL,
    pinned TINYINT NOT NULL DEFAULT 0,
    closed TINYINT NOT NULL DEFAULT 0,
    solution_id BIGINT UNSIGNED NULL,
    merged_into BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (board_id, deleted_at, pinned, active_at, id),
    INDEX (deleted_at, id),
    FOREIGN KEY (board_id) REFERENCES goldensite_forum_boards(id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    author_id INT NULL,
    body MEDIUMTEXT NOT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (topic_id, deleted_at, id),
    INDEX (author_id, deleted_at, id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_topic_tags (
    topic_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (topic_id, tag_id),
    INDEX (tag_id, topic_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (tag_id) REFERENCES goldensite_forum_tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_poll_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(120) NOT NULL,
    UNIQUE (topic_id, id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_votes (
    topic_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (topic_id, user_id),
    INDEX (topic_id, option_id),
    FOREIGN KEY (topic_id, option_id) REFERENCES goldensite_forum_poll_options(topic_id, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_follows (
    topic_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    email_enabled TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (topic_id, user_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    reply_id BIGINT UNSIGNED NULL,
    reason VARCHAR(12) NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, read_at, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_reactions (
    reply_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(12) NOT NULL,
    PRIMARY KEY (reply_id, user_id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_preferences (
    user_id INT PRIMARY KEY,
    direct_email TINYINT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_forum_attachments (
    id CHAR(32) PRIMARY KEY,
    topic_id BIGINT UNSIGNED NOT NULL,
    reply_id BIGINT UNSIGNED NULL,
    mime VARCHAR(32) NOT NULL,
    label VARCHAR(120) NOT NULL,
    INDEX (topic_id, reply_id),
    FOREIGN KEY (topic_id) REFERENCES goldensite_forum_topics(id),
    FOREIGN KEY (reply_id) REFERENCES goldensite_forum_replies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_echoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    region CHAR(2) NULL,
    audio_mime VARCHAR(40) NULL,
    audio_key CHAR(32) NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (hidden_at,id),
    INDEX (region,hidden_at,id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_waves (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    echo_id BIGINT UNSIGNED NOT NULL,
    user_id INT NULL,
    body VARCHAR(500) NOT NULL,
    hidden_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (echo_id,hidden_at,id),
    FOREIGN KEY (echo_id) REFERENCES goldensite_social_echoes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_resonances (
    echo_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    attentive_seconds SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (echo_id,user_id),
    FOREIGN KEY (echo_id) REFERENCES goldensite_social_echoes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_social_presence (
    user_id INT PRIMARY KEY,
    region CHAR(2) NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at,region),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goldensite_editorial_queue (
    entity ENUM('page','post') NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    state ENUM('review','approved','scheduled','stale') NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    scheduled_by BIGINT UNSIGNED DEFAULT NULL,
    publish_at BIGINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (entity, document_id),
    KEY editorial_due (state, publish_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
