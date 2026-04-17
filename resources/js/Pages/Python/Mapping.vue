<template>
    <Head title="Mapping Excel" />

    <AuthenticatedLayout>
        <template #header> Mapping Excel </template>

        <div class="p-6 space-y-6">
            <!-- HEADER -->
            <div class="bg-white p-6 rounded shadow">
                <h1 class="text-xl font-bold mb-4">🗺️ Mapping Excel</h1>

                <!-- <p class="text-sm text-gray-600">
                    File: <b>{{ filePath }}</b>
                </p>

                <div>
                    <p>File Path: {{ filePath }}</p>
                    <p>Agent ID: {{ agent_id }}</p>
                    <p>Report ID: {{ report_id }}</p>
                </div> -->

                <!-- SELECT SHEET -->
                <div v-if="sheets.length" class="mt-4">
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

                <!-- ACTION -->
                <button
                    @click="processExport"
                    class="w-full mt-6 bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700"
                >
                    📊 Export Excel
                </button>

                <button
                    @click="saveMapping"
                    class="w-full mt-3 bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700"
                >
                    💾 Simpan Mapping
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { usePage, Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// =====================
// INERTIA PROPS
// =====================
const page = usePage();

const filePath = page.props.filePath;
const agent_id = page.props.agent_id;
const report_id = page.props.report_id;

// =====================
// STATE
// =====================
const sheets = ref([]);
const headers = ref([]);
const selectedSheetName = ref(null);
const mapping = ref({});

// TARGET FIELD
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

// =====================
// INIT SCAN EXCEL
// =====================
onMounted(async () => {
    if (!filePath) {
        alert("File path tidak ditemukan!");
        return;
    }

    try {
        const res = await axios.post("/python/scan", {
            file_path: filePath,
        });

        sheets.value = res.data.sheets;

        if (sheets.value.length) {
            selectedSheetName.value = sheets.value[0].sheet;
            headers.value = sheets.value[0].headers;
        }
    } catch (err) {
        console.error(err);
        alert("Gagal scan file Excel");
    }
});

// =====================
// CHANGE SHEET
// =====================
const changeSheet = () => {
    const selected = sheets.value.find(
        (s) => s.sheet === selectedSheetName.value,
    );

    if (!selected) return;

    headers.value = selected.headers;
    mapping.value = {}; // reset mapping
};

// =====================
// EXPORT EXCEL
// =====================
const processExport = async () => {
    try {
        console.log("Memulai proses download...");

        const res = await axios.post(
            "/python/process",
            {
                file_path: filePath,
                agent_id: page.props.agent_id,
                report_id: page.props.report_id,
                mapping: mapping.value,
                sheet: selectedSheetName.value,
            },
            {
                // SANGAT PENTING: Beritahu axios bahwa ini adalah file
                responseType: "blob",
            },
        );

        // 1. Ambil data binary dari response
        const blob = new Blob([res.data], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        // 2. Buat Link URL sementara di memori browser
        const url = window.URL.createObjectURL(blob);

        // 3. Buat elemen 'a' bayangan untuk memicu download
        const link = document.createElement("a");
        link.href = url;

        // Beri nama file hasil downloadnya
        const fileName = `Export_Yuri_${selectedSheetName.value}.xlsx`;
        link.setAttribute("download", fileName);

        document.body.appendChild(link);
        link.click();

        // 4. Bersihkan memori
        link.remove();
        window.URL.revokeObjectURL(url);

        console.log("File berhasil didownload!");
    } catch (err) {
        console.error("Gagal mendownload file:");

        // Trik membaca error JSON saat responseType-nya blob
        if (err.response && err.response.data instanceof Blob) {
            const text = await err.response.data.text();
            console.error("Detail Error:", text);
        } else {
            console.error(err);
        }
        alert("Terjadi kesalahan saat export.");
    }
};

const saveMapping = async () => {
    try {
        const page = usePage();

        await axios.post("/mapping/save", {
            mapping: mapping.value,
            sheet: selectedSheetName.value,
            agent_report_id: page.props.report_id,
            agent_id: page.props.agent_id,
        });

        alert("Mapping berhasil disimpan!");
    } catch (err) {
        console.error("ERROR SAVE MAPPING:", err.response?.data || err);
        alert(err.response?.data?.message || "Gagal simpan mapping");
    }
};
</script>
