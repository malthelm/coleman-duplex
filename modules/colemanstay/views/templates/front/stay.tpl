<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{if $coleman_detail == 'one'}Apartment One — {elseif $coleman_detail == 'two'}Apartment Two — {/if}560 Coleman · Highland Park</title>
<meta name="description" content="A private preview of two apartments at 560 Coleman Avenue, Highland Park, Los Angeles.">
<link rel="stylesheet" href="/modules/colemanstay/views/css/stay.css">
<script src="/modules/colemanstay/views/js/stay.js" defer></script>
</head>
<body>
<a class="skip" href="#main">{l s='Skip to content' mod='colemanstay'}</a>
<div class="demo-bar">{l s='Private preview' mod='colemanstay'} <span aria-hidden="true">·</span> {l s='Test requests only · No reservations or payments' mod='colemanstay'}</div>
<header class="site-header wrap">
<a class="wordmark" href="/" aria-label="{l s='560 Coleman home' mod='colemanstay'}"><span class="home-mark" aria-hidden="true">⌂</span> 560 Coleman</a>
<nav class="search-bar" aria-label="{l s='Plan your stay' mod='colemanstay'}"><a href="/#apartments">{l s='Highland Park' mod='colemanstay'}</a><a href="#arrival" data-focus="arrival">{l s='Add dates' mod='colemanstay'}</a><a href="#guests" data-focus="guests">{l s='Add guests' mod='colemanstay'} <span class="search-icon" aria-hidden="true">⌕</span></a></nav>
<a class="header-request" href="#request">{l s='Request a stay' mod='colemanstay'} <span aria-hidden="true">↗</span></a>
</header>
<main id="main" class="wrap">
<div class="page-heading">
{if $coleman_detail}<a class="back-link" href="/#apartments">← {l s='All apartments' mod='colemanstay'}</a>{/if}
<h1>{if $coleman_detail == 'one'}{l s='Apartment One at 560 Coleman' mod='colemanstay'}{elseif $coleman_detail == 'two'}{l s='Apartment Two at 560 Coleman' mod='colemanstay'}{else}{l s='Find your place in Highland Park' mod='colemanstay'}{/if}</h1>
<p><span aria-hidden="true">⌖</span> {l s='560 Coleman Avenue · Los Angeles, California' mod='colemanstay'}</p>
</div>
<div class="listing-layout">
<div class="listing-content">
{if !$coleman_detail}
<section id="apartments" aria-labelledby="apartments-title">
<div class="section-top"><h2 id="apartments-title">{l s='Two apartments. One address.' mod='colemanstay'}</h2><span>{l s='Short & longer stays' mod='colemanstay'}</span></div>
<div class="apartment-grid">
<article class="apartment"><a class="apartment-image" href="/apartments/one/"><img src="/modules/colemanstay/views/img/560-lower-kitchen.jpg" alt="{l s='Kitchen and living area of the lower apartment at 560 Coleman Avenue, from its rental listing' mod='colemanstay'}" width="960" height="720" fetchpriority="high"><span class="image-tag">{l s='Lower apartment · 560' mod='colemanstay'}</span></a><div class="apartment-heading"><h3><a href="/apartments/one/">{l s='Apartment One' mod='colemanstay'}</a></h3><span aria-hidden="true">↗</span></div><p>{l s='Highland Park, Los Angeles' mod='colemanstay'}</p><p>{l s='Availability & details confirmed by the host' mod='colemanstay'}</p><a class="card-link" href="/apartments/one/">{l s='Explore apartment' mod='colemanstay'} <span aria-hidden="true">→</span></a></article>
<article class="apartment"><a class="apartment-image alternate" href="/apartments/two/"><img src="/modules/colemanstay/views/img/562-upper-kitchen.jpg" alt="{l s='Kitchen and living area of the upper apartment at 562 Coleman Avenue, from its rental listing' mod='colemanstay'}" width="960" height="720"><span class="image-tag">{l s='Upper apartment · 562' mod='colemanstay'}</span></a><div class="apartment-heading"><h3><a href="/apartments/two/">{l s='Apartment Two' mod='colemanstay'}</a></h3><span aria-hidden="true">↗</span></div><p>{l s='Highland Park, Los Angeles' mod='colemanstay'}</p><p>{l s='Availability & details confirmed by the host' mod='colemanstay'}</p><a class="card-link" href="/apartments/two/">{l s='Explore apartment' mod='colemanstay'} <span aria-hidden="true">→</span></a></article>
</div></section>
{else}
<figure class="detail-image">{if $coleman_detail == 'one'}<img src="/modules/colemanstay/views/img/560-lower-kitchen.jpg" alt="{l s='Kitchen and living area of the lower apartment at 560 Coleman Avenue, from its rental listing' mod='colemanstay'}" width="960" height="720" fetchpriority="high"><figcaption>{l s='560 Coleman · Lower apartment' mod='colemanstay'} · <a href="https://www.zillow.com/homedetails/560-Coleman-Ave-Los-Angeles-CA-90042/20686978_zpid/" target="_blank" rel="noopener noreferrer">{l s='Listing photo on Zillow' mod='colemanstay'}<span class="sr-only"> — {l s='opens in a new tab' mod='colemanstay'}</span></a></figcaption>{else}<img src="/modules/colemanstay/views/img/562-upper-kitchen.jpg" alt="{l s='Kitchen and living area of the upper apartment at 562 Coleman Avenue, from its rental listing' mod='colemanstay'}" width="960" height="720" fetchpriority="high"><figcaption>{l s='562 Coleman · Upper apartment' mod='colemanstay'} · <a href="https://www.zillow.com/homedetails/562-Coleman-Ave-Los-Angeles-CA-90042/465318518_zpid/" target="_blank" rel="noopener noreferrer">{l s='Listing photo on Zillow' mod='colemanstay'}<span class="sr-only"> — {l s='opens in a new tab' mod='colemanstay'}</span></a></figcaption>{/if}</figure>
<section class="detail-description"><h2>{l s='An apartment in Highland Park' mod='colemanstay'}</h2><p>{l s='Part of the duplex at 560 Coleman Avenue, Los Angeles. Enquire about a short visit or a longer stay directly with the host.' mod='colemanstay'}</p><p class="muted">{l s='Photos come from the apartment’s rental listing. Furnishings, guest capacity, amenities, rates and availability should be confirmed with the host for your stay.' mod='colemanstay'}</p></section>
{/if}
<section class="how-it-works" aria-labelledby="how-title"><h2 id="how-title">{l s='A more personal way to stay' mod='colemanstay'}</h2><div class="benefit"><span class="benefit-icon" aria-hidden="true">▦</span><div><h3>{l s='Your dates, your plans' mod='colemanstay'}</h3><p>{l s='A few nights or a longer stay. Share what you have in mind.' mod='colemanstay'}</p></div></div><div class="benefit"><span class="benefit-icon" aria-hidden="true">☏</span><div><h3>{l s='Talk directly with the host' mod='colemanstay'}</h3><p>{l s='Discuss availability, pricing and the details before booking.' mod='colemanstay'}</p></div></div><div class="benefit"><span class="benefit-icon" aria-hidden="true">✓</span><div><h3>{l s='Start with a request' mod='colemanstay'}</h3><p>{l s='Sending a request does not reserve dates or charge you.' mod='colemanstay'}</p></div></div></section>
<section class="neighbourhood" id="neighbourhood"><h2>{l s='Where you’ll be' mod='colemanstay'}</h2><p>{l s='Highland Park, Los Angeles, California' mod='colemanstay'}</p><a class="location-link" href="https://www.google.com/maps/search/?api=1&amp;query=560+Coleman+Ave+Los+Angeles+CA" target="_blank" rel="noopener noreferrer"><span class="location-icon" aria-hidden="true">⌖</span><span><strong>{l s='560 Coleman Avenue' mod='colemanstay'}</strong><span>{l s='View the address on Google Maps' mod='colemanstay'}</span></span><span aria-hidden="true">↗</span><span class="sr-only">{l s='opens in a new tab' mod='colemanstay'}</span></a></section>
</div>
<section class="request-section" id="request" aria-labelledby="request-title"><div class="request-panel"><div class="request-heading"><h2 id="request-title">{l s='Request a stay' mod='colemanstay'}</h2><p>{l s='Choose your dates. Get in touch directly.' mod='colemanstay'}</p></div>
{if $coleman_receipt}
<div class="success" role="status" tabindex="-1" id="request-result"><span class="success-icon" aria-hidden="true">✓</span><p class="eyebrow">{l s='SAVED TO THE DEMO INBOX' mod='colemanstay'}</p><h3>{l s='Your request is in.' mod='colemanstay'}</h3><p>{l s='Reference' mod='colemanstay'} <strong>{$coleman_receipt|escape:'html':'UTF-8'}</strong></p><p>{l s='The host can now review this test enquiry. This is not a booking confirmation. No email has been sent and your dates have not been held.' mod='colemanstay'}</p><a class="button" href="/#request">{l s='Try another request' mod='colemanstay'} ↗</a></div>
{elseif !$coleman_ready}
<div role="status"><h3>{l s='The apartments are being prepared.' mod='colemanstay'}</h3><p>{l s='The request form will be available once both apartments are set up. Please check back shortly.' mod='colemanstay'}</p><a class="text-link" href="/">{l s='Refresh the preview' mod='colemanstay'}</a></div>
{else}
{if $coleman_errors}<div class="form-errors" role="alert" tabindex="-1" id="request-result"><h3>{l s='Please check your request.' mod='colemanstay'}</h3><ul>{foreach $coleman_errors as $error}<li>{$error|escape:'html':'UTF-8'}</li>{/foreach}</ul></div>{/if}
<form method="post" action="/#request" id="stay-form" data-saving="{l s='Saving your demo request…' mod='colemanstay'}">
<input type="hidden" name="csrf" value="{$coleman_csrf|escape:'html':'UTF-8'}"><input type="hidden" name="request_token" value="{$coleman_token|escape:'html':'UTF-8'}">
<div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<label for="apartment">{l s='Which apartment?' mod='colemanstay'}</label><select name="apartment" id="apartment" required><option value="">{l s='Choose an apartment' mod='colemanstay'}</option><option value="one" {if $coleman_form.apartment == 'one'}selected{/if}>{l s='Apartment One' mod='colemanstay'}</option><option value="two" {if $coleman_form.apartment == 'two'}selected{/if}>{l s='Apartment Two' mod='colemanstay'}</option></select>
<div class="form-row"><div><label for="arrival">{l s='Arrival' mod='colemanstay'}</label><input id="arrival" name="arrival" type="date" min="{$coleman_today|escape:'html':'UTF-8'}" value="{$coleman_form.arrival|escape:'html':'UTF-8'}" required></div><div><label for="departure">{l s='Departure' mod='colemanstay'}</label><input id="departure" name="departure" type="date" min="{$coleman_today|escape:'html':'UTF-8'}" value="{$coleman_form.departure|escape:'html':'UTF-8'}" required></div></div>
<div class="form-row name-row"><div><label for="guest-name">{l s='Your name' mod='colemanstay'}</label><input id="guest-name" name="guest_name" autocomplete="name" maxlength="120" value="{$coleman_form.guest_name|escape:'html':'UTF-8'}" required></div><div><label for="guests">{l s='Guests' mod='colemanstay'}</label><input id="guests" name="guests" type="number" min="1" max="8" value="{$coleman_form.guests|escape:'html':'UTF-8'}" required aria-describedby="capacity-note"></div></div><p class="field-note" id="capacity-note">{l s='Guest capacity is subject to host confirmation.' mod='colemanstay'}</p>
<label for="email">{l s='Email address' mod='colemanstay'}</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="{$coleman_form.email|escape:'html':'UTF-8'}" required>
<label for="message">{l s='Anything you’d like us to know?' mod='colemanstay'} <span class="optional">{l s='optional' mod='colemanstay'}</span></label><textarea id="message" name="message" rows="3" maxlength="2000" placeholder="{l s='Dates flexible? Planning a longer stay?' mod='colemanstay'}">{$coleman_form.message|escape:'html':'UTF-8'}</textarea>
<label class="checkbox"><input type="checkbox" name="demo_consent" value="1" required><span>{l s='I understand this is a demo request, not a confirmed booking. My test details will be stored for the host to review.' mod='colemanstay'}</span></label>
<button class="button submit" type="submit">{l s='Send demo request' mod='colemanstay'} <span aria-hidden="true">↗</span></button><p id="submit-status" class="field-note" role="status"></p>
</form>
{/if}
</div><p class="request-note">{l s='Private demo: test details only. Requests are saved to the host inbox. No email, payment or reservation.' mod='colemanstay'}</p></section>
</div>
</main>
<footer class="site-footer"><div class="wrap footer-inner"><p>© 560 Coleman <span>·</span> {l s='Highland Park, Los Angeles' mod='colemanstay'}</p><div><a href="/host-admin/">{l s='Host login' mod='colemanstay'}</a><a href="https://github.com/malthelm/coleman-duplex/tree/codex/coleman-demo">{l s='Source · QloApps / OSL-3.0' mod='colemanstay'}</a></div></div></footer>
</body></html>
