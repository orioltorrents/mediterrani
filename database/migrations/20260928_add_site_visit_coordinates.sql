ALTER TABLE site_visits
    ADD COLUMN latitude DECIMAL(9, 6) NULL AFTER region,
    ADD COLUMN longitude DECIMAL(9, 6) NULL AFTER latitude,
    ADD KEY idx_visits_geo (country_code, region, latitude, longitude);
