{literal}
	<tr v-for="vm in vms">
		<td class="col-md-6">{{ vm.label }}</td>
		<td>{{ vm.cost }}</td>
	</tr>
<tr>
	<td>{/literal}{$lang->TotalCost}{literal}</td>
	<td>{{ cost }}</td>
</tr>
{/literal}