<section class="calendar-exchange" aria-labelledby="exchange-title">
<h2 id="exchange-title">{l s='Calendar files' mod='colemanstay'}</h2>
<p class="field-hint">{l s='Exchange a one-time copy of blocked dates for the selected apartment. Files do not stay in sync with another platform.' mod='colemanstay'}</p>
<div class="exchange-layout">
<div><h3>{l s='Import blocked dates' mod='colemanstay'}</h3>
<form class="host-form" method="post" enctype="multipart/form-data" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=calendar#exchange-title" data-saving="{l s='Reading file…' mod='colemanstay'}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="id_product" value="{$selected_unit|intval}"><input type="hidden" name="month" value="{$calendar_month|escape:'html':'UTF-8'}">
<fieldset{if !$can_review} disabled{/if}><legend class="sr-only">{l s='Choose calendar file' mod='colemanstay'}</legend>
<label>{l s='Calendar name (host only)' mod='colemanstay'}<input type="text" name="import_label" maxlength="80" value="{$import_label|escape:'html':'UTF-8'}" required></label>
<label>{l s='iCalendar file (.ics)' mod='colemanstay'}<input type="file" name="calendar_file" accept=".ics,text/calendar" aria-describedby="calendar-file-hint" required></label>
<p class="field-hint" id="calendar-file-hint">{l s='Up to 512 KB and 500 events. All-day, non-recurring dates only. You will review the dates before importing.' mod='colemanstay'}</p>
<button class="button secondary" type="submit" name="decision" value="calendar_preview">{l s='Preview dates' mod='colemanstay'}</button>
</fieldset></form></div>
<div><h3>{l s='Export blocked dates' mod='colemanstay'}</h3>
<p class="field-hint">{l s='Download current and upcoming blocks and reserved stays. Each event says Unavailable; guest details and private notes are excluded.' mod='colemanstay'}</p>
<form method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=calendar"><input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="id_product" value="{$selected_unit|intval}"><button class="button secondary" type="submit" name="decision" value="calendar_export"{if !$can_review} disabled{/if}>{l s='Download calendar' mod='colemanstay'}</button></form>
</div></div>
{if $calendar_preview}
<div class="import-preview" role="region" aria-labelledby="preview-title"><h3 id="preview-title">{l s='Review imported dates' mod='colemanstay'}</h3>
<p>{$calendar_preview.label|escape:'html':'UTF-8'} · {foreach $units as $unit}{if $unit.id_product == $selected_unit}{$unit.name|escape:'html':'UTF-8'} · {$unit.address|escape:'html':'UTF-8'}{/if}{/foreach}</p>
<p class="field-hint">{l s='Nothing has been imported yet. Only nights within the next two years are included; checkout day remains available. Overlapping events in this file are combined.' mod='colemanstay'}</p>
{if $calendar_preview.ignored || $calendar_preview.clipped}<p class="field-hint">{l s='Events skipped (cancelled, free, or outside the date window):' mod='colemanstay'} {$calendar_preview.ignored|intval}. {l s='Events shortened to the date window:' mod='colemanstay'} {$calendar_preview.clipped|intval}.</p>{/if}
{if $calendar_preview.ranges}
<ul class="import-dates">{foreach $calendar_preview.ranges as $range}<li><time datetime="{$range.arrival|escape:'html':'UTF-8'}">{$range.arrival|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</time> — <time datetime="{$range.departure|escape:'html':'UTF-8'}">{$range.departure|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</time> ({l s='available again' mod='colemanstay'})</li>{/foreach}</ul>
<p class="field-hint">{l s='Import adds date blocks. Later changes or cancellations in the original calendar will not remove them; use Release block here. Dates already fully blocked are skipped. Any partial conflict cancels the whole import.' mod='colemanstay'}</p>
<form class="host-form" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=calendar" data-saving="{l s='Importing…' mod='colemanstay'}"><input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="id_product" value="{$selected_unit|intval}"><input type="hidden" name="month" value="{$calendar_month|escape:'html':'UTF-8'}"><input type="hidden" name="preview_payload" value="{$calendar_preview.payload|escape:'html':'UTF-8'}"><input type="hidden" name="preview_signature" value="{$calendar_preview.signature|escape:'html':'UTF-8'}"><button class="button primary" type="submit" name="decision" value="calendar_imported"{if !$can_review} disabled{/if}>{l s='Import these dates' mod='colemanstay'}</button></form>
{else}<p>{l s='No blocked dates to import. Choose another file to try again.' mod='colemanstay'}</p>{/if}
</div>{/if}
</section>
