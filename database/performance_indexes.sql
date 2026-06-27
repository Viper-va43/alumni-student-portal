-- Where2Go performance indexes
-- Safe to rerun: each index is created only when missing.

DROP PROCEDURE IF EXISTS where2go_add_index_if_missing;

DELIMITER //

CREATE PROCEDURE where2go_add_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_ddl TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND INDEX_NAME = p_index_name
    ) THEN
        SET @where2go_index_ddl = p_ddl;
        PREPARE where2go_index_stmt FROM @where2go_index_ddl;
        EXECUTE where2go_index_stmt;
        DEALLOCATE PREPARE where2go_index_stmt;
    END IF;
END//

DELIMITER ;

CALL where2go_add_index_if_missing(
    'businesses',
    'idx_businesses_approval_id',
    'ALTER TABLE businesses ADD INDEX idx_businesses_approval_id (approval_status, business_id)'
);

CALL where2go_add_index_if_missing(
    'business_locations',
    'idx_business_locations_business_location',
    'ALTER TABLE business_locations ADD INDEX idx_business_locations_business_location (business_id, location_id)'
);

CALL where2go_add_index_if_missing(
    'business_photos',
    'idx_business_photos_business_id_id',
    'ALTER TABLE business_photos ADD INDEX idx_business_photos_business_id_id (business_id, id)'
);

CALL where2go_add_index_if_missing(
    'bookings',
    'idx_bookings_location_date_status_time',
    'ALTER TABLE bookings ADD INDEX idx_bookings_location_date_status_time (location_id, date, status, time_slot)'
);

CALL where2go_add_index_if_missing(
    'bookings',
    'idx_bookings_location_status_date_time',
    'ALTER TABLE bookings ADD INDEX idx_bookings_location_status_date_time (location_id, status, date, time_slot)'
);

CALL where2go_add_index_if_missing(
    'bookings',
    'idx_bookings_customer_date_time',
    'ALTER TABLE bookings ADD INDEX idx_bookings_customer_date_time (customer_id, date, time_slot)'
);

CALL where2go_add_index_if_missing(
    'bookings',
    'idx_bookings_customer_location_date_time',
    'ALTER TABLE bookings ADD INDEX idx_bookings_customer_location_date_time (customer_id, location_id, date, time_slot)'
);

CALL where2go_add_index_if_missing(
    'customer_saved_places',
    'idx_customer_saved_places_customer_created',
    'ALTER TABLE customer_saved_places ADD INDEX idx_customer_saved_places_customer_created (customer_id, created_at)'
);

CALL where2go_add_index_if_missing(
    'business_offers',
    'idx_business_offers_active_window',
    'ALTER TABLE business_offers ADD INDEX idx_business_offers_active_window (business_id, is_active, start_date, end_date)'
);

CALL where2go_add_index_if_missing(
    'business_reviews',
    'idx_business_reviews_business_created',
    'ALTER TABLE business_reviews ADD INDEX idx_business_reviews_business_created (business_id, created_at)'
);

CALL where2go_add_index_if_missing(
    'customer_place_visits',
    'idx_customer_place_visits_business_viewed',
    'ALTER TABLE customer_place_visits ADD INDEX idx_customer_place_visits_business_viewed (business_id, viewed_at)'
);

CALL where2go_add_index_if_missing(
    'customer_checkins',
    'idx_customer_checkins_business_checked_at',
    'ALTER TABLE customer_checkins ADD INDEX idx_customer_checkins_business_checked_at (business_id, checked_in_at)'
);

DROP PROCEDURE IF EXISTS where2go_add_index_if_missing;
