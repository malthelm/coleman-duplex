<div class="page-heading"><h1>{l s='Rental details' mod='colemanstay'}</h1><p>{l s='Keep the details for each apartment in one place. Leave undecided values blank.' mod='colemanstay'}</p></div>
<p class="review-note">{l s='Draft details stay in the host dashboard. Only mark them confirmed after Jim has reviewed them. All amounts are in USD.' mod='colemanstay'}</p>
<div class="settings-list">
{foreach $units as $unit}
<section class="unit-settings" aria-labelledby="unit-{$unit.id_product|intval}">
<div class="request-heading"><div><h2 id="unit-{$unit.id_product|intval}">{$unit.name|escape:'html':'UTF-8'}</h2><p class="muted">{$unit.address|escape:'html':'UTF-8'}</p></div><span class="status {if $unit.owner_confirmed}approved{else}pending{/if}">{if $unit.owner_confirmed}{l s='Owner confirmed' mod='colemanstay'}{else}{l s='Draft details' mod='colemanstay'}{/if}</span></div>
<form class="host-form" method="post" action="{$inbox_url|escape:'html':'UTF-8'}&amp;view=settings" data-saving="{l s='Saving…' mod='colemanstay'}">
<input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}"><input type="hidden" name="id_product" value="{$unit.id_product|intval}"><input type="hidden" name="revision" value="{$unit.revision|intval}">
<fieldset{if !$can_review} disabled{/if}><legend class="sr-only">{$unit.name|escape:'html':'UTF-8'} {l s='rental details' mod='colemanstay'}</legend>
<div class="settings-fields">
<label>{l s='Nightly rent (USD)' mod='colemanstay'}<input type="number" name="nightly_rate" min="0.01" max="999999.99" step="0.01" inputmode="decimal" value="{$unit.nightly_rate|escape:'html':'UTF-8'}"></label>
<label>{l s='Monthly rent (USD)' mod='colemanstay'}<input type="number" name="monthly_rate" min="0.01" max="999999.99" step="0.01" inputmode="decimal" value="{$unit.monthly_rate|escape:'html':'UTF-8'}"></label>
<label>{l s='Cleaning fee (USD)' mod='colemanstay'}<input type="number" name="cleaning_fee" min="0" max="999999.99" step="0.01" inputmode="decimal" value="{$unit.cleaning_fee|escape:'html':'UTF-8'}"></label>
<label>{l s='Minimum stay (nights)' mod='colemanstay'}<input type="number" name="min_nights" min="1" max="730" step="1" inputmode="numeric" value="{$unit.min_nights|escape:'html':'UTF-8'}"></label>
<label>{l s='Maximum guests' mod='colemanstay'}<input type="number" name="max_guests" min="1" max="8" step="1" inputmode="numeric" value="{$unit.max_guests|escape:'html':'UTF-8'}"></label>
</div>
<label>{l s='House rules' mod='colemanstay'}<textarea name="house_rules" rows="4" maxlength="4000">{$unit.house_rules|escape:'html':'UTF-8'}</textarea></label>
<label class="check-label"><input type="checkbox" name="owner_confirmed" value="1"{if $unit.owner_confirmed} checked{/if}><span>{l s='Jim has confirmed these rental details.' mod='colemanstay'}</span></label>
<p class="field-hint">{l s='Confirmed details appear on the apartment page. Confirmed minimum stays and guest limits apply to new requests and reservations.' mod='colemanstay'}</p>
<button class="button primary" type="submit" name="decision" value="settings_saved">{l s='Save rental details' mod='colemanstay'}</button>
</fieldset></form>
</section>
{/foreach}
</div>
