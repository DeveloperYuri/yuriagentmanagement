<script setup>
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
});

// 🔥 FORMAT NUMBER AMAN
const formatNumber = (val) => {
    if (!val) return "-";
    return Number(val).toLocaleString("id-ID");
};
</script>

<template>
    <Head title="Preview Import Data" />

    <div class="min-h-screen bg-gray-100 p-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Preview Data Import</h2>

            <!-- EMPTY -->
            <div v-if="!data.length" class="text-center text-gray-400 py-10">
                Data kosong
            </div>

            <!-- TABLE -->
            <div v-else class="overflow-auto max-h-[70vh] border rounded">
                <table class="min-w-full text-sm text-left">
                    <!-- HEADER -->
                    <thead class="bg-gray-200 sticky top-0">
                        <tr>
                            <th class="px-4 py-2">Nama Agen</th>
                            <th class="px-4 py-2">Kode Customer</th>
                            <th class="px-4 py-2">Nama Customer</th>
                            <th class="px-4 py-2">Alamat</th>
                            <th class="px-4 py-2">Nomer Tlp/Hp Customer</th>
                            <th class="px-4 py-2">Invoice</th>
                            <th class="px-4 py-2">Tanggal</th>
                            <th class="px-4 py-2">Tipe Customer</th>
                            <th class="px-4 py-2">Sales</th>
                            <th class="px-4 py-2">Kode SKU</th>
                            <th class="px-4 py-2">Nama SKU</th>
                            <th class="px-4 py-2 text-right">Qty Terjual</th>
                            <th class="px-4 py-2 text-right">Diskon 1</th>
                            <th class="px-4 py-2 text-right">Diskon 2</th>
                            <th class="px-4 py-2 text-right">Diskon 3</th>
                            <th class="px-4 py-2 text-right">Diskon 4</th>
                            <th class="px-4 py-2 text-right">Diskon 5</th>
                            <th class="px-4 py-2 text-right">Diskon 6</th>
                            <th class="px-4 py-2 text-right">Quantity Bonus</th>
                            <th class="px-4 py-2 text-right">Rafraksi</th>
                            <th class="px-4 py-2 text-right">Total Invoice Value</th>
                        </tr>
                    </thead>

                    <!-- BODY -->
                    <tbody>
                        <tr 
                            v-for="(item, i) in data" 
                            :key="i"
                            class="border-t hover:bg-gray-50"
                        >
                            <td class="px-4 py-2">{{ item.nama_agen || '-' }}</td>
                            <td class="px-4 py-2">{{ item.kode_customer || '-' }}</td>
                            <td class="px-4 py-2">{{ item.nama_customer || '-' }}</td>
                            <td class="px-4 py-2">{{ item.alamat_customer || '-' }}</td>
                            <td class="px-4 py-2">{{ item.nomor_telepon_hp_customer || '-' }}</td>
                            <td class="px-4 py-2">{{ item.invoice_nomor_agen || '-' }}</td>
                            <td class="px-4 py-2">{{ item.tanggal_invoice || '-' }}</td>
                            <td class="px-4 py-2">{{ item.tipe_customer || '-' }}</td>
                            <td class="px-4 py-2">{{ item.sales || '-' }}</td>
                            <td class="px-4 py-2">{{ item.sku_kode_agen || '-' }}</td>
                            <td class="px-4 py-2">{{ item.nama_sku || '-' }}</td>
                            <td class="px-4 py-2">{{ item.qty_terjual || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon1 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon2 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon3 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon4 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon5 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.diskon6 || '-' }}</td>
                            <td class="px-4 py-2">{{ item.quantity_bonus || '-' }}</td>
                            <td class="px-4 py-2">{{ item.rafraksi || '-' }}</td>
                            <td class="px-4 py-2">{{ item.total_invoice_value || '-' }}</td>

                            <!-- <td class="px-4 py-2 text-right">
                                {{ formatNumber(item.qty_terjual) }}
                            </td>

                            <td class="px-4 py-2 text-right font-semibold">
                                {{ formatNumber(item.total_invoice_value) }}
                            </td> -->
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER -->
            <div class="mt-6 flex justify-between">
                <button
                    @click="$inertia.get(route('reports.index'))"
                    class="text-gray-500 hover:underline"
                >
                    Kembali
                </button>

                <button
                    class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700"
                >
                    Simpan ke Database
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    layout: null,
};
</script>
