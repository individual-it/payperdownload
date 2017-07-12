ALTER TABLE #__payperdownloadplus_resource_licenses
	CHANGE `resource_price` `resource_price` VARCHAR(255) NOT NULL;
ALTER TABLE #__payperdownloadplus_resource_licenses
	CHANGE `download_expiration` `download_expiration` VARCHAR(255) DEFAULT 365;
