<div class="page-heading"><a class="back-link" href="{$inbox_url|escape:'html':'UTF-8'}&amp;view=applications">← {l s='All applications' mod='colemanstay'}</a></div>
{if !$application}<div class="empty"><h1>{l s='Application not found' mod='colemanstay'}</h1><p>{l s='Return to the list to choose an available application.' mod='colemanstay'}</p></div>
{else}
<div class="request-heading"><div><h1>{$application.guest_name|escape:'html':'UTF-8'}</h1><p class="reference">CA-{$application.id_coleman_application|string_format:'%05d'|escape:'html':'UTF-8'} · {if $application.id_product == $application_one}{l s='Apartment One · 560' mod='colemanstay'}{else}{l s='Apartment Two · 562' mod='colemanstay'}{/if}</p></div><span class="status pending">{$application_labels[$application.stage]|escape:'html':'UTF-8'}</span></div>
<div class="message-layout application-context">
<section>
<h2>{l s='Their plans' mod='colemanstay'}</h2><p class="application-text">{if $application.message}{$application.message|escape:'html':'UTF-8'}{else}{l s='No additional message.' mod='colemanstay'}{/if}</p>
<form class="host-form application-progress" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=application&amp;application_id={$application.id_coleman_application|intval}" data-saving="{l s='Saving…' mod='colemanstay'}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="application_id" value="{$application.id_coleman_application|intval}"><input type="hidden" name="revision" value="{$application_form.revision|escape:'html':'UTF-8'}">
<fieldset{if !$can_review} disabled{/if}><legend class="sr-only">{l s='Application progress' mod='colemanstay'}</legend>
<label>{l s='Stage' mod='colemanstay'}<select name="stage">{foreach $application_labels as $key => $label}<option value="{$key|escape:'html':'UTF-8'}"{if $application_form.stage == $key} selected{/if}>{$label|escape:'html':'UTF-8'}</option>{/foreach}</select></label>
<label>{l s='Next action' mod='colemanstay'} <span class="muted">{l s='optional' mod='colemanstay'}</span><input name="next_action" maxlength="250" value="{$application_form.next_action|escape:'html':'UTF-8'}" placeholder="{l s='For example, arrange a viewing' mod='colemanstay'}"></label>
<label>{l s='Add a private host note' mod='colemanstay'} <span class="muted">{l s='optional' mod='colemanstay'}</span><textarea name="note" rows="5" maxlength="4000" aria-describedby="note-guidance">{$application_form.note|escape:'html':'UTF-8'}</textarea></label>
<p class="field-hint" id="note-guidance">{l s='Keep notes relevant to the rental. Do not enter identity numbers, banking details or screening reports. Notes are saved to the history below.' mod='colemanstay'}</p>
<p class="field-hint">{l s='Changing a stage does not reserve dates, send a message or sign a lease.' mod='colemanstay'}</p>
<button class="button primary" name="decision" value="application_saved" type="submit">{l s='Save progress' mod='colemanstay'}</button></fieldset></form>
<section class="application-history" aria-label="{l s='Application history' mod='colemanstay'}"><h2>{l s='History' mod='colemanstay'}</h2>
{if count($application_history) == 100}<p class="field-hint">{l s='Showing the latest 100 updates. Earlier entries remain stored.' mod='colemanstay'}</p>{/if}
<ol>{foreach $application_history as $entry}<li><strong>{$application_labels[$entry.stage]|escape:'html':'UTF-8'}</strong> · <time>{$entry.date_add|escape:'html':'UTF-8'}</time><p class="field-hint">{l s='Host' mod='colemanstay'} #{$entry.employee_id|intval}</p>{if $entry.next_action}<p>{l s='Next action' mod='colemanstay'}: {$entry.next_action|escape:'html':'UTF-8'}</p>{/if}{if $entry.note}<p>{$entry.note|escape:'html':'UTF-8'}</p>{/if}</li>{/foreach}<li><strong>{l s='Enquiry received' mod='colemanstay'}</strong> · <time>{$application.date_add|escape:'html':'UTF-8'}</time></li></ol></section>
</section>
<aside class="message-context"><h2>{l s='Enquiry details' mod='colemanstay'}</h2><dl>
<dt>{l s='Email' mod='colemanstay'}</dt><dd><a href="mailto:{$application.email|escape:'html':'UTF-8'}">{$application.email|escape:'html':'UTF-8'}</a></dd>
<dt>{l s='Approximate move-in' mod='colemanstay'}</dt><dd>{if $application.move_in}{$application.move_in|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}{else}{l s='Flexible' mod='colemanstay'}{/if}</dd>
<dt>{l s='Intended duration' mod='colemanstay'}</dt><dd>{$application.duration|escape:'html':'UTF-8'}</dd>
<dt>{l s='Occupants' mod='colemanstay'}</dt><dd>{$application.occupants|intval}</dd>
<dt>{l s='Pets' mod='colemanstay'}</dt><dd>{$application.pets|escape:'html':'UTF-8'}</dd>
</dl></aside></div>
{/if}
