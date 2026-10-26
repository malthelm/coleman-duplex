<div class="page-heading"><h1>{l s='Guest message' mod='colemanstay'}</h1><p>{l s='Review the wording, save your draft, then download it for your email app.' mod='colemanstay'}</p></div>
{if !$message_request}
<p>{l s='This request is not available.' mod='colemanstay'}</p><a href="{$inbox_url|escape:'html':'UTF-8'}">{l s='Back to requests' mod='colemanstay'}</a>
{else}
<a class="back-link" href="{$inbox_url|escape:'html':'UTF-8'}&amp;status={$message_request.status|escape:'html':'UTF-8'}">{l s='Back to requests' mod='colemanstay'}</a>
<div class="message-layout">
<section class="message-editor" aria-labelledby="draft-heading">
<h2 id="draft-heading">{if $message_request.status == 'reserved'}{l s='Date confirmation draft' mod='colemanstay'}{elseif $message_request.status == 'declined'}{l s='Decline draft' mod='colemanstay'}{else}{l s='Follow-up draft' mod='colemanstay'}{/if}</h2>
{if $message_stale}<div class="notice error" role="status">{l s='This draft was saved before the request changed. Review the current status and dates, update the wording, and save it again before downloading.' mod='colemanstay'}</div>{/if}
{if $message_draft}
<form class="host-form message-form" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=message&amp;request_id={$message_request.id_coleman_request|intval}" data-saving="{l s='Saving…' mod='colemanstay'}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="request_id" value="{$message_request.id_coleman_request|intval}"><input type="hidden" name="kind" value="{$message_request.status|escape:'html':'UTF-8'}"><input type="hidden" name="request_revision" value="{$message_draft.request_revision|intval}"><input type="hidden" name="revision" value="{$message_draft.revision|intval}">
<fieldset{if !$can_review} disabled{/if}><legend class="sr-only">{l s='Edit guest message' mod='colemanstay'}</legend>
<label>{l s='Subject' mod='colemanstay'}<input type="text" name="subject" value="{$message_draft.subject|escape:'html':'UTF-8'}" maxlength="180" required></label>
<label>{l s='Message' mod='colemanstay'}<textarea name="body" rows="17" maxlength="10000" required>{$message_draft.body|escape:'html':'UTF-8'}</textarea></label>
<button class="button primary" type="submit" name="decision" value="draft_saved"{if $message_request.status == 'pending'} disabled{/if}>{l s='Save draft' mod='colemanstay'}</button>
</fieldset></form>
<div class="draft-download">
<form class="draft-export" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=message&amp;request_id={$message_request.id_coleman_request|intval}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="request_id" value="{$message_request.id_coleman_request|intval}"><input type="hidden" name="kind" value="{$message_request.status|escape:'html':'UTF-8'}"><input type="hidden" name="request_revision" value="{$message_draft.request_revision|intval}"><input type="hidden" name="revision" value="{$message_draft.revision|intval}">
<button class="button secondary" type="submit" name="decision" value="draft_download"{if !$can_review || !$message_downloadable} disabled{/if}>{l s='Download saved email draft' mod='colemanstay'}</button>
</form>
<p class="field-hint" id="draft-save-hint" aria-live="polite" data-unsaved="{l s='You have unsaved changes. Save the draft before downloading.' mod='colemanstay'}">{if $message_downloadable}{l s='Downloads the saved wording as an .eml file. Open it in your email app and review it before sending.' mod='colemanstay'}{else}{l s='Save the draft before downloading.' mod='colemanstay'}{/if}</p>
</div>
{else}<p>{l s='Approve or decline this request before preparing a reply. Reserve its dates to prepare a date confirmation.' mod='colemanstay'}</p>{/if}
</section>
<aside class="message-context" aria-labelledby="recipient-heading"><h2 id="recipient-heading">{l s='Request details' mod='colemanstay'}</h2>
<span class="status {$message_request.status|escape:'html':'UTF-8'}">{$status_labels[$message_request.status]|escape:'html':'UTF-8'}</span>
<p class="reference">{$message_request.reference|escape:'html':'UTF-8'} · {$message_request.apartment_name|escape:'html':'UTF-8'}</p>
<dl><dt>{l s='To' mod='colemanstay'}</dt><dd>{$message_request.guest_name|escape:'html':'UTF-8'}<br>{$message_request.email|escape:'html':'UTF-8'}</dd>
<dt>{l s='Check-in' mod='colemanstay'}</dt><dd>{$message_request.arrival|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</dd><dt>{l s='Check-out' mod='colemanstay'}</dt><dd>{$message_request.departure|date_format:'%b %e, %Y'|escape:'html':'UTF-8'}</dd><dt>{l s='Guests' mod='colemanstay'}</dt><dd>{$message_request.guests|intval}</dd></dl>
<p class="field-hint">{l s='Saving or downloading never sends a message. This private demo has no email delivery enabled.' mod='colemanstay'}</p>
{if $message_draft && $message_draft.updated_at}<p class="field-hint">{l s='Draft saved' mod='colemanstay'} {$message_draft.updated_at|date_format:'%b %e, %Y · %H:%M'|escape:'html':'UTF-8'}</p>{/if}
</aside></div>
{/if}
