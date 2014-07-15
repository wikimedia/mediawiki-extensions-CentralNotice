-- Update to add the devices we currently target

INSERT INTO /*$wgDBprefix*/cn_known_devices (dev_name, dev_display_label) VALUES
	('android', '{{int:centralnotice-devicetype-android}}'),
	('iphone', '{{int:centralnotice-devicetype-iphone}}'),
	('ipad', '{{int:centralnotice-devicetype-ipad}}'),
	('unknown', '{{int:centralnotice-devicetype-unknown}}');

