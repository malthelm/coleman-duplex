<?php
/** Idempotent, additive private-demo setup. Does not create reservations. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN');
require dirname(__DIR__, 2).'/config/config.inc.php';
$context = Context::getContext();
$context->employee = new Employee(1);
if (!Validate::isLoadedObject($context->employee)) { throw new RuntimeException('Install QloApps first'); }
Configuration::updateValue('PS_MAIL_METHOD', 3);
Configuration::updateValue('PS_SSL_ENABLED', 1);
Configuration::updateValue('PS_SSL_ENABLED_EVERYWHERE', 1);
Configuration::updateValue('PS_REWRITING_SETTINGS', 0);
Configuration::updateValue('PS_SHOP_ENABLE', 1);
Configuration::updateValue('PS_TIMEZONE', 'America/Los_Angeles');
foreach (array('hotelreservationsystem', 'colemanstay') as $name) {
    if (!Module::isInstalled($name)) {
        $module = Module::getInstanceByName($name);
        $module->populateData = false;
        if (!$module->install()) { throw new RuntimeException('Module installation failed: '.$name); }
    }
}
$hotelId = (int)Configuration::get('COLEMAN_HOTEL');
if (!$hotelId) {
    $hotel = new HotelBranchInformation();
    foreach (Language::getLanguages(false) as $lang) {
        $hotel->hotel_name[$lang['id_lang']] = '560 Coleman';
        $hotel->description[$lang['id_lang']] = 'Private duplex demo. Property details, pricing and occupancy limits are unconfirmed.';
    }
    $hotel->email = 'host@coleman.example';
    $hotel->active = 0; // Draft inventory, unavailable for instant booking.
    $hotel->check_in = '15:00:00'; // Required software defaults, not published house policy.
    $hotel->check_out = '11:00:00';
    $hotel->id_country = Country::getByIso('US');
    $hotel->id_state = (int)State::getIdByIso('CA', $hotel->id_country);
    $hotel->city = 'Los Angeles';
    $hotel->address = '560 Coleman Avenue';
    $hotel->zipcode = '';
    $hotel->phone = '';
    if (!$hotel->addWs()) { throw new RuntimeException('Hotel creation failed'); }
    $hotelId = (int)$hotel->id;
    Configuration::updateValue('COLEMAN_HOTEL', $hotelId);
}
foreach (array('ONE' => 'Apartment One', 'TWO' => 'Apartment Two') as $key => $name) {
    if (Configuration::get('COLEMAN_PRODUCT_'.$key)) { continue; }
    $product = new Product();
    foreach (Language::getLanguages(false) as $lang) {
        $product->name[$lang['id_lang']] = $name;
        $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
        $product->description[$lang['id_lang']] = 'Demo inventory only. Photos, capacity, amenities and rates to be confirmed.';
    }
    $product->id_shop_default = $context->shop->id;
    $product->id_category_default = 2;
    $product->price = 0; // Internal placeholder, never published as a rate.
    $product->active = 0;
    $product->booking_product = true;
    $product->show_at_front = 0;
    $product->is_virtual = 1;
    $product->id_selling_object = 1;
    if (!$product->add()) { throw new RuntimeException('Apartment creation failed'); }
    $product->addToCategories(array(2));
    $type = new HotelRoomType();
    $type->id_product = $product->id;
    $type->id_hotel = $hotelId;
    $type->id_selling_object = 1;
    $type->adults = $type->max_adults = $type->max_guests = 1;
    $type->children = $type->max_children = 0;
    if (!$type->add()) { throw new RuntimeException('Room type creation failed'); }
    $room = new HotelRoomInformation();
    $room->id_product = $product->id;
    $room->id_hotel = $hotelId;
    $room->room_num = $name;
    $room->id_status = HotelRoomInformation::STATUS_INACTIVE;
    $room->comment = 'Demo only; capacity and availability unconfirmed.';
    if (!$room->add()) { throw new RuntimeException('Room creation failed'); }
    Configuration::updateValue('COLEMAN_PRODUCT_'.$key, $product->id);
}
ColemanCalendar::installSchema();
echo "Coleman demo ready: two draft apartments, email disabled, request-only storefront.\n";
