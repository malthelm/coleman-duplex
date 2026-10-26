<input type="hidden" name="decision" value="tenant_action">
<input type="hidden" name="application_id" value="{$rental.id|intval}">
<input type="hidden" name="revision" value="{$rental.revision|intval}">
<input type="hidden" name="section" value="{$rental_section|escape:'html':'UTF-8'}">
{if $is_portal}<input type="hidden" name="csrf" value="{$portal_csrf|escape:'html':'UTF-8'}">{/if}
