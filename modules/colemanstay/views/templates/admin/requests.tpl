<!doctype html>
<html lang="{$host_language|escape:'html':'UTF-8'}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{l s='Booking requests' mod='colemanstay'} · 560 Coleman</title>
<link rel="stylesheet" href="/modules/colemanstay/views/css/tokens.css">
<link rel="stylesheet" href="/modules/colemanstay/views/css/requests.css?v=1">
<script src="/modules/colemanstay/views/js/requests.js?v=1" defer></script>
</head>
<body>
<a class="skip" href="#main">{l s='Skip to content' mod='colemanstay'}</a>
<div class="demo-bar">{l s='Private preview · Test requests only' mod='colemanstay'}</div>
<header class="site-header wrap">
<a class="wordmark" href="{$inbox_url|escape:'html':'UTF-8'}"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-8h6v8"/></svg>560 Coleman</a>
<nav aria-label="{l s='Host navigation' mod='colemanstay'}"><a href="/">{l s='View website' mod='colemanstay'}<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 18 18 6M6 6h12v12"/></svg></a><a href="{$inbox_url|escape:'html':'UTF-8'}&amp;logout=1">{l s='Sign out' mod='colemanstay'}</a></nav>
</header>
<main id="main" class="wrap">
<div class="page-heading"><h1>{l s='Booking requests' mod='colemanstay'}</h1><p>{l s='A little closer to their stay in Highland Park.' mod='colemanstay'}</p></div>
<p class="review-note">{l s='Approve a request to continue with the guest. Dates are not reserved and no email is sent automatically.' mod='colemanstay'}</p>
{foreach $review_errors as $error}<div class="notice error" role="alert">{$error|escape:'html':'UTF-8'}</div>{/foreach}
{if $notice == 'approved'}<div class="notice success" role="status">{l s='Request approved. Contact the guest to arrange the next steps.' mod='colemanstay'}</div>
{elseif $notice == 'declined'}<div class="notice success" role="status">{l s='Request declined. You can find it in Declined and move it back to pending.' mod='colemanstay'}</div>
{elseif $notice == 'pending'}<div class="notice success" role="status">{l s='Request moved back to pending.' mod='colemanstay'}</div>{/if}
<nav class="filters" aria-label="{l s='Filter booking requests' mod='colemanstay'}">
{foreach $status_labels as $key => $label}<a href="{$inbox_url|escape:'html':'UTF-8'}&amp;status={$key|escape:'html':'UTF-8'}"{if $filter == $key} aria-current="page"{/if}>{$label|escape:'html':'UTF-8'}<span class="count">{$counts[$key]|intval}</span></a>{/foreach}
</nav>
<section class="request-list" aria-label="{$status_labels[$filter]|escape:'html':'UTF-8'}">
{foreach $requests as $request}
<article class="request" aria-labelledby="guest-{$request.id_coleman_request|intval}">
<div class="request-heading"><div><h2 id="guest-{$request.id_coleman_request|intval}">{$request.guest_name|escape:'html':'UTF-8'}</h2><p class="reference">{$request.reference|escape:'html':'UTF-8'} <span aria-hidden="true">·</span> {l s='Received' mod='colemanstay'} {$request.date_add|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</p></div><span class="status {$request.status|escape:'html':'UTF-8'}">{$status_labels[$request.status]|escape:'html':'UTF-8'}</span></div>
<div class="request-body">
<div class="stay-details"><a class="apartment" href="/apartments/{$request.unit|escape:'html':'UTF-8'}/">{if $request.unit == 'one'}<img src="/modules/colemanstay/views/img/560-lower-kitchen.jpg" alt="" width="88" height="72"><span><strong>{l s='Apartment One' mod='colemanstay'}</strong><span>{l s='Lower apartment · 560' mod='colemanstay'}</span></span>{else}<img src="/modules/colemanstay/views/img/562-upper-kitchen.jpg" alt="" width="88" height="72"><span><strong>{l s='Apartment Two' mod='colemanstay'}</strong><span>{l s='Upper apartment · 562' mod='colemanstay'}</span></span>{/if}</a>
<dl class="dates"><div><dt>{l s='Check-in' mod='colemanstay'}</dt><dd><time datetime="{$request.arrival|escape:'html':'UTF-8'}">{$request.arrival|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</time></dd></div><div><dt>{l s='Check-out' mod='colemanstay'}</dt><dd><time datetime="{$request.departure|escape:'html':'UTF-8'}">{$request.departure|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</time></dd></div></dl>
<p class="stay-length">{$request.nights|intval} {if $request.nights == 1}{l s='night' mod='colemanstay'}{else}{l s='nights' mod='colemanstay'}{/if} <span aria-hidden="true">·</span> {$request.guests|intval} {if $request.guests == 1}{l s='guest' mod='colemanstay'}{else}{l s='guests' mod='colemanstay'}{/if}</p></div>
<div class="guest-message"><h3>{l s='From the guest' mod='colemanstay'}</h3>{if $request.message}<p class="message">{$request.message|escape:'html':'UTF-8'}</p>{else}<p class="muted">{l s='No message included.' mod='colemanstay'}</p>{/if}<a class="email" href="mailto:{$request.email|escape:'html':'UTF-8'}">{$request.email|escape:'html':'UTF-8'}</a></div>
</div>
<div class="request-footer"><p>{if $request.reviewed_at}{l s='Last reviewed' mod='colemanstay'} {$request.reviewed_at|date_format:'%b %e, %Y · %H:%M'|escape:'html':'UTF-8'}{else}{l s='Ready for your review' mod='colemanstay'}{/if}</p>
{if $can_review}<form class="decision-form" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;status={$filter|escape:'html':'UTF-8'}" aria-label="{$request.reference|escape:'html':'UTF-8'}" data-saving="{l s='Saving…' mod='colemanstay'}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="request_id" value="{$request.id_coleman_request|intval}"><input type="hidden" name="revision" value="{$request.revision|intval}">
{if $request.status == 'pending'}<button type="submit" class="button secondary" name="decision" value="declined">{l s='Decline' mod='colemanstay'}</button><button type="submit" class="button primary" name="decision" value="approved">{l s='Approve request' mod='colemanstay'}</button>{else}<button type="submit" class="button secondary" name="decision" value="pending">{l s='Move to pending' mod='colemanstay'}</button>{/if}
</form>{else}<p>{l s='View-only access' mod='colemanstay'}</p>{/if}</div>
</article>
{foreachelse}
<div class="empty"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 5 3 3 5-5"/></svg><h2>{if $filter == 'pending'}{l s='You’re all caught up' mod='colemanstay'}{elseif $filter == 'approved'}{l s='No approved requests yet' mod='colemanstay'}{elseif $filter == 'declined'}{l s='No declined requests' mod='colemanstay'}{else}{l s='Your next stay starts here' mod='colemanstay'}{/if}</h2><p>{if $filter == 'pending' || $filter == 'all'}{l s='New requests from the website will appear here for you to review.' mod='colemanstay'}{else}{l s='Requests will appear here once you’ve reviewed them.' mod='colemanstay'}{/if}</p>{if $filter != 'pending'}<a href="{$inbox_url|escape:'html':'UTF-8'}">{l s='View pending requests' mod='colemanstay'}</a>{/if}</div>
{/foreach}
</section>
{if $pages > 1}<nav class="pagination" aria-label="{l s='Request pages' mod='colemanstay'}">{if $page > 1}<a href="{$inbox_url|escape:'html':'UTF-8'}&amp;status={$filter|escape:'html':'UTF-8'}&amp;page={$page-1}">{l s='Previous' mod='colemanstay'}</a>{/if}<span>{l s='Page' mod='colemanstay'} {$page|intval} / {$pages|intval}</span>{if $page < $pages}<a href="{$inbox_url|escape:'html':'UTF-8'}&amp;status={$filter|escape:'html':'UTF-8'}&amp;page={$page+1}">{l s='Next' mod='colemanstay'}</a>{/if}</nav>{/if}
</main>
<footer class="site-footer wrap"><span>560 Coleman · {l s='Host dashboard' mod='colemanstay'}</span><a href="{$settings_url|escape:'html':'UTF-8'}">{l s='Advanced QloApps settings' mod='colemanstay'}</a></footer>
</body>
</html>
