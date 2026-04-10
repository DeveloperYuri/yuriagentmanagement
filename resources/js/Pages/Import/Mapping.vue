<script setup>
import { ref, watch } from "vue";
import { useForm, Head } from "@inertiajs/vue3";

const props = defineProps({
    excelHeaders: Array,
    dbColumns: Array,
    tempPath: String,
    agents: {
        type: Array,
        default: () => [],
    },
});

// 🔹 state agent
const selectedAgentId = ref("");

// 🔹 NORMALIZE
const normalize = (text) => text?.toLowerCase().replace(/[_\s]/g, "");

// 🔹 ALIAS
const mappingAlias = {
    nama_agen: ["namaagen"],
    kode_customer: ["kodecustomer"],
    nama_customer: ["namacustomer"],
    alamat_customer: ["alamat"],
    invoice_nomor_agen: ["kodetransaksi", "referensi"],
    tanggal_invoice: ["tanggal"],
    sales: ["namasalesman"],
    sku_kode_agen: ["kodebarang"],
    nama_sku: ["namabarang"],
    qty_terjual: ["totqty", "qty"],
    total_invoice_value: ["total"],
};

// 🔹 STATE MAPPING
const mapping = ref({});

// 🔹 AUTO MAPPING
watch(
    () => [props.excelHeaders, props.dbColumns],
    ([headers, dbCols]) => {
        if (!headers?.length || !dbCols?.length) return;

        const temp = {};
        dbCols.forEach((col) => {
            const aliases = mappingAlias[col] || [];
            const match = headers.find((header) => {
                if (!header) return false;
                const h = normalize(header);
                const c = normalize(col);
                if (h.includes(c)) return true;
                return aliases.some((alias) => h.includes(alias));
            });
            temp[col] = match || "";
        });
        mapping.value = temp;
    },
    { immediate: true }
);

// 🔹 FORM
const form = useForm({
    filePath: props.tempPath,
    mapping: {},
    agent_id: null,
});

// 🔹 SAVE MAPPING PER AGENT
const saveMapping = () => {
    if (!selectedAgentId.value) {
        alert("Pilih agent dulu!");
        return;
    }

    form.mapping = { ...mapping.value };
    form.agent_id = selectedAgentId.value;

    form.post(route("import.saveMapping"), {
        preserveState: true,
        forceFormData: true,
        onSuccess: () =>
            console.log("Mapping tersimpan untuk agent id:", selectedAgentId.value),
        onError: (err) => console.log("ERROR:", err),
    });
};

// 🔹 SUBMIT IMPORT
const submitMapping = () => {
    if (!selectedAgentId.value) {
        alert("Pilih agent dulu!");
        return;
    }

    form.mapping = { ...mapping.value };
    form.filePath = props.tempPath;
    form.agent_id = selectedAgentId.value;

    form.post(route("import.process"), {
        preserveState: true,
        forceFormData: true,
        onSuccess: () => console.log("Import sukses"),
        onError: (err) => console.log("ERROR:", err),
    });
};
</script>

<!-- <script setup>
import { ref, watch } from "vue";
import { useForm, Head } from "@inertiajs/vue3";

const props = defineProps({
    excelHeaders: {
        type: Array,
        default: () => [],
    },
    dbColumns: {
        type: Array,
        default: () => [],
    },
    tempPath: String,

    agents: {
        type: Array,
        default: () => [],
    },
});

// 🔥 NORMALIZE (biar matching lebih pintar)
const normalize = (text) => text?.toLowerCase().replace(/[_\s]/g, "");
const selectedAgentName = ref(""); // kosong dulu, nanti pilih dari dropdown

// 🔥 ALIAS (biar auto match lebih akurat)
const mappingAlias = {
    nama_agen: ["namaagen"],
    kode_customer: ["kodecustomer"],
    nama_customer: ["namacustomer"],
    alamat_customer: ["alamat"],
    invoice_nomor_agen: ["kodetransaksi", "referensi"],
    tanggal_invoice: ["tanggal"],
    sales: ["namasalesman"],
    sku_kode_agen: ["kodebarang"],
    nama_sku: ["namabarang"],
    qty_terjual: ["totqty", "qty"],
    total_invoice_value: ["total"],
};

// state mapping
const mapping = ref({});

// 🔥 AUTO MAPPING (FIXED)
watch(
    () => [props.excelHeaders, props.dbColumns],
    ([headers, dbCols]) => {
        if (!headers?.length || !dbCols?.length) return;

        const temp = {};

        dbCols.forEach((col) => {
            const aliases = mappingAlias[col] || [];

            const match = headers.find((header) => {
                if (!header) return false;

                const h = normalize(header);
                const c = normalize(col);

                // 1. direct match
                if (h.includes(c)) return true;

                // 2. alias match
                return aliases.some((alias) => h.includes(alias));
            });

            temp[col] = match || "";
        });

        mapping.value = temp;
    },
    { immediate: true },
);

const form = useForm({
    filePath: props.tempPath,
    mapping: {},
});

const submitMapping = () => {
    console.log("CLICKED");
    console.log("MAPPING ASLI:", mapping.value);

    form.mapping = { ...mapping.value }; // 🔥 FIX PENTING (clone object)
    form.filePath = props.tempPath;

    form.post(route("import.process"), {
        preserveState: true,
        forceFormData: true,
        onError: (err) => console.log("ERROR:", err),
        onSuccess: () => console.log("SUCCESS"),
    });
};

const saveMapping = () => {
    if (!selectedAgentId.value) {
        alert('Pilih agent dulu!');
        return;
    }

    form.mapping = { ...mapping.value };
    form.agent_id = selectedAgentId.value; // simpan mapping per agent

    form.post(route('import.saveMapping'), {
        preserveState: true,
        forceFormData: true,
        onSuccess: () => console.log('Mapping tersimpan untuk agent id:', selectedAgentId.value),
    });
}; -->

// const saveMapping = () => {
//     console.log("SAVE MAPPING:", mapping.value);

//     form.mapping = { ...mapping.value };
//     form.filePath = props.tempPath;

//     form.post(route("import.saveMapping"), { // pake route saveMapping
//         preserveState: true,
//         forceFormData: true,
//         onSuccess: () => console.log("Mapping berhasil disimpan"),
//         onError: (err) => console.log("ERROR:", err),
//     });
// };

// const saveMapping = () => {
//     console.log("SAVE MAPPING:", mapping.value);

//     Inertia.post(
//         route("import.saveMapping"),
//         {
//             mapping: mapping.value,
//         },
//         {
//             onSuccess: () => alert("Mapping berhasil disimpan!"),
//             onError: (err) => console.log("ERROR SAVE MAPPING:", err),
//         },
//     );
// };
<!-- </script> -->

<template>
    <Head title="Mapping Data Agent" />

    <div class="min-h-screen bg-gray-100 p-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-6">Mapping Kolom Excel</h2>

            <p class="text-gray-600 mb-8 text-sm">
                Sesuaikan kolom dari file Excel agent (kanan) dengan kolom di
                sistem kita (kiri).
            </p>

            <div class="mb-4">
                <label class="block font-medium text-gray-700 mb-1"
                    >Pilih Agent</label
                >
                <select
                    v-model="selectedAgentId"
                    class="w-full border-gray-300 rounded-md shadow-sm"
                >
                    <option value="">-- Pilih Agent --</option>
                    <option
                        v-for="agent in agents"
                        :key="agent.id"
                        :value="agent.id"
                    >
                        {{ agent.name }}
                    </option>
                </select>
            </div>

            <!-- EMPTY STATE -->
            <div
                v-if="!excelHeaders.length || !dbColumns.length"
                class="text-center text-gray-400 py-10"
            >
                Data tidak tersedia / file kosong
            </div>

            <!-- MAPPING -->
            <div v-else class="space-y-4">
                <div
                    v-for="dbCol in dbColumns"
                    :key="dbCol"
                    class="flex items-center gap-4"
                >
                    <div class="w-1/3">
                        <span class="font-medium text-gray-700 capitalize">
                            {{ dbCol.replaceAll("_", " ") }}
                        </span>
                    </div>

                    <div class="text-gray-400">→</div>

                    <div class="flex-1">
                        <select
                            v-model="mapping[dbCol]"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">-- Lewati Kolom Ini --</option>
                            <option
                                v-for="header in excelHeaders"
                                :key="header"
                                :value="header"
                            >
                                {{ header }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ACTION -->
            <div class="mt-10 flex justify-between items-center border-t pt-6">
                <button
                    @click="$inertia.get(route('reports.index'))"
                    class="text-gray-500 hover:underline"
                >
                    Batal
                </button>

                <button
                    type="button"
                    @click="saveMapping"
                    class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700"
                >
                    Simpan Mapping
                </button>
                <button
                    type="button"
                    @click="submitMapping"
                    :disabled="form.processing"
                    class="bg-indigo-600 text-white px-6 py-2 rounded-md font-semibold hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ form.processing ? "Memproses..." : "Impor Sekarang" }}
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
