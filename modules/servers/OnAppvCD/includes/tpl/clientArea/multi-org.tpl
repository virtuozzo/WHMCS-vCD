{literal}
	<tr v-for="pool in pools">
		<td class="col-md-6">{{ pool.label }}</td>
		<td>{{ pool.cost }}</td>
	</tr>
<tr>
	<td>{/literal}{$lang->TotalCost}{literal}</td>
	<td>{{ cost }}</td>
</tr>
{/literal}