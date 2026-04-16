<template>
    <Head title="Mapping Excel" />

    <AuthenticatedLayout>
        <template #header>
            Mapping Excel
        </template>

        <div class="p-6 space-y-6">
            <!-- HEADER -->
            <div class="bg-white p-6 rounded shadow">
                <h1 class="text-xl font-bold mb-4">🗺️ Mapping Excel</h1>

                <!-- SELECT SHEET -->
                <div v-if="sheets.length">
                    <label class="font-bold text-sm">Pilih Sheet</label>
                    <select
                        v-model="selectedSheetName"
                        @change="changeSheet"
                        class="w-full border p-2 rounded mt-1"
                    >
                        <option
                            v-for="s in sheets"
                            :key="s.sheet"
                            :value="s.sheet"
                        >
                            {{ s.sheet }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- MAPPING -->
            <div v-if="headers.length" class="bg-white p-6 rounded shadow">
                <div
                    v-for="field in targetFields"
                    :key="field"
                    class="flex items-center mb-3 border-b pb-2"
                >
                    <label class="w-1/3 font-semibold text-sm">
                        {{ field }}
                    </label>

                    <select
                        v-model="mapping[field]"
                        class="w-2/3 border p-2 rounded"
                    >
                        <option value="">-- pilih kolom --</option>

                        <option v-for="h in headers" :key="h" :value="h">
                            {{ h }}
                        </option>
                    </select>
                </div>

                <button
                    @click="processExport"
                    class="w-full mt-6 bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700"
                >
                    📊 Export Excel
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

const sheets = ref([]);
const headers = ref([]);
const selectedSheetName = ref(null);
const mapping = ref({});

// TARGET FIELD (FULL)
const targetFields = [
    "Nama Agen",
    "Kode Customer",
    "Nama Customer",
    "Alamat Customer",
    "Nomor Telepon/HP Customer",
    "Invoice Nomor Agen",
    "Tanggal Invoice",
    "Tipe Customer",
    "Sales",
    "SKU Kode Agen",
    "Nama SKU",
    "Qty Terjual (PCS)",
    "% Diskon 1 (Reguler)",
    "% Diskon 2 (Cash)",
    "% Diskon 3 (DC Free)",
    "% Diskon 4 (Promo 1)",
    "% Diskon 5 (Promo 2)",
    "% Diskon 6 (Rp)",
    "Quantity Bonus",
    "Rafraksi",
    "Total Invoice Value",
];

// 🔥 SCAN FILE PAS MASUK PAGE

onMounted(async () => {
    const page = usePage();
    const filePath = page.props.filePath;

    console.log("FILE PATH DARI INERTIA:", filePath); // 🔥

    if (!filePath) {
        alert("File path tidak ditemukan!");
        return;
    }

    const res = await axios.post("/python/scan", {
        file_path: filePath,
    });

    sheets.value = res.data.sheets;

    if (sheets.value.length) {
        selectedSheetName.value = sheets.value[0].sheet;
        headers.value = sheets.value[0].headers;
    }
});

// onMounted(async () => {
//     const filePath = new URLSearchParams(window.location.search).get(
//         "filePath",
//     );

//     const res = await axios.post("/python/scan", {
//         file_path: filePath,
//     });

//     sheets.value = res.data.sheets;

//     // default sheet pertama
//     if (sheets.value.length) {
//         selectedSheetName.value = sheets.value[0].sheet;
//         headers.value = sheets.value[0].headers;
//     }
// });

// 🔄 GANTI SHEET
const changeSheet = () => {
    const selected = sheets.value.find(
        (s) => s.sheet === selectedSheetName.value,
    );

    headers.value = selected.headers;

    // reset mapping biar gak kacau
    mapping.value = {};
};

// 🚀 EXPORT
const processExport = async () => {
    const filePath = new URLSearchParams(window.location.search).get(
        "filePath",
    );

    const res = await axios.post(
        "/python/process",
        {
            file_path: filePath,
            mapping: mapping.value,
            sheet: selectedSheetName.value,
        },
        {
            responseType: "blob",
        },
    );

    const url = window.URL.createObjectURL(new Blob([res.data]));
    const link = document.createElement("a");
    link.href = url;
    link.download = "RESULT_YURI.xlsx";
    link.click();
};
</script>
