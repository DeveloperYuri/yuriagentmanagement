<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { usePage, Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "vue-toastification";

// =====================
// PROPS & DATA
// =====================
const page = usePage();
const filePath = page.props.filePath;
const agent_id = page.props.agent_id;
const report_id = page.props.report_id;
const toast = useToast();

// =====================
// STATE
// =====================
const mappingSheets = ["Kode Produk JIM", "Invoice Agen", "Stock Agen"];
const sheets = ref([]); // Isi dari scan excel (sheet name & headers)
const headers = ref([]);
const selectedSheetName = ref(mappingSheets[0]);
const selectedSourceSheet = ref("");
const mapping = ref({});

// Menyimpan semua konfigurasi mapping untuk 3 tab
const allMappings = ref({
    "Kode Produk JIM": { source_sheet: "", mapping: {} },
    "Invoice Agen": { source_sheet: "", mapping: {} },
    "Stock Agen": { source_sheet: "", mapping: {} },
});

// State untuk Matching Master
const comparisonResults = ref([]);
const isComparing = ref(false);

// =====================
// CONFIGURATION: TARGET FIELDS
// =====================
const sheetFields = {
    "Kode Produk JIM": [
        "No",
        "Item Code",
        "Item Name",
        "Item / Box",
        "Item Group",
        "Kode SKU Agen",
    ],
    "Invoice Agen": [
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
    ],
    "Stock Agen": [
        "KODE",
        "Kode SKU Agen",
        "Kode SKU JIM",
        "Kode JIM",
        "Stock",
    ],
};

// =====================
// LIFECYCLE: INIT
// =====================
onMounted(async () => {
    try {
        const res = await axios.post("/python/scan", {
            file_path: filePath,
        });
        sheets.value = res.data.sheets;
        // Default ke tab pertama
        selectSheet(mappingSheets[0]);
    } catch (err) {
        console.error(err);
        toast.error("Gagal menscan file Excel");
    }
});

// =====================
// LOGIC: UI ACTIONS
// =====================

// Pindah Tab Mapping
const selectSheet = (sheetName) => {
    selectedSheetName.value = sheetName;
    comparisonResults.value = []; // Reset preview matching

    // Ambil data yang tersimpan di allMappings untuk tab ini
    const savedData = allMappings.value[sheetName];
    selectedSourceSheet.value = savedData.source_sheet || "";
    mapping.value = savedData.mapping || {};

    if (selectedSourceSheet.value) {
        loadHeaders(selectedSourceSheet.value);
    } else {
        headers.value = [];
    }
};

// Load Headers saat ganti Source Sheet
const loadHeaders = (sheetName) => {
    const found = sheets.value.find((s) => s.sheet === sheetName);
    headers.value = found ? found.headers : [];
    // Simpan ke state allMappings
    allMappings.value[selectedSheetName.value].source_sheet = sheetName;
};

// Simpan Mapping ke DB (Per Tab)
const saveMapping = async () => {
    try {
        // Update state lokal dulu
        allMappings.value[selectedSheetName.value].mapping = mapping.value;

        await axios.post("/mapping/save", {
            mapping: mapping.value,
            sheet: selectedSheetName.value,
            source_sheet: selectedSourceSheet.value,
            agent_report_id: report_id,
            agent_id: agent_id,
        });

        toast.success(
            `Mapping untuk ${selectedSheetName.value} berhasil disimpan`,
        );
    } catch (err) {
        console.error(err);
        toast.error("Gagal menyimpan mapping");
    }
};

// Jalankan Matching Master Data (Hanya di Tab Kode Produk JIM)
const compareWithMaster = async () => {
    if (!selectedSourceSheet.value) {
        toast.error("Pilih Sheet sumber terlebih dahulu!");
        return;
    }

    isComparing.value = true;
    comparisonResults.value = [];

    try {
        const res = await axios.post("/python/compare-master", {
            file_path: filePath,
            source_sheet: selectedSourceSheet.value,
            mapping: mapping.value,
        });

        if (res.data.error) {
            toast.error(res.data.error);
        } else {
            comparisonResults.value = res.data.results;
            toast.success("Proses matching selesai!");
        }
    } catch (err) {
        console.error(err);
        toast.error("Gagal melakukan matching ke database");
    } finally {
        isComparing.value = false;
    }
};

// Export Akhir (Mengirim 3 Mapping Sekaligus)
const processExport = async () => {
    try {
        toast.info("Sedang memproses export 3 sheet...");

        const res = await axios.post(
            "/python/process-multi",
            {
                file_path: filePath,
                agent_id,
                report_id,
                // 🔥 FIX PENTING: pastikan object
                mappings: { ...allMappings.value },
            },
            {
                responseType: "blob", // WAJIB
            },
        );

        // 🔥 FIX PENTING: handle blob dengan benar
        const blob = new Blob([res.data], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        const url = window.URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.setAttribute(
            "download",
            `EXPORT_YURI_${agent_id}_${Date.now()}.xlsx`,
        );

        document.body.appendChild(link);
        link.click();

        // cleanup
        link.remove();
        window.URL.revokeObjectURL(url);

        toast.success("Export berhasil diunduh!");
    } catch (err) {
        console.error("ERROR RAW:", err);

        if (err.response && err.response.data) {
            // 🔥 CONVERT BLOB → TEXT
            const reader = new FileReader();

            reader.onload = () => {
                try {
                    const text = reader.result;
                    console.error("ERROR TEXT:", text);

                    const json = JSON.parse(text);
                    console.error("ERROR JSON:", json);

                    toast.error(json.error || "Terjadi error");
                } catch (e) {
                    console.error("Gagal parse error:", e);
                    console.error("Isi mentah:", reader.result);
                    toast.error("Error tidak terbaca");
                }
            };

            reader.readAsText(err.response.data);
        } else {
            toast.error("Gagal melakukan export data");
        }
    }
};
</script>

<template>
    <Head title="Multi Sheet Mapping" />

    <AuthenticatedLayout>
        <template #header> Multi Sheet Mapping </template>

        <div class="p-6 space-y-6 max-w-7xl mx-auto">
            <div
                class="bg-white p-6 rounded-lg shadow-sm border border-gray-100"
            >
                <h1
                    class="text-xl font-extrabold text-gray-800 mb-4 flex items-center gap-2"
                >
                    <span>🧠</span> Mapping 3 Sheet (Fixed Output)
                </h1>

                <div class="flex gap-2 flex-wrap mb-6">
                    <button
                        v-for="s in mappingSheets"
                        :key="s"
                        @click="selectSheet(s)"
                        class="px-5 py-2.5 text-sm rounded-md border transition-all font-bold"
                        :class="
                            selectedSheetName === s
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-md'
                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'
                        "
                    >
                        {{ s }}
                    </button>
                </div>

                <div
                    class="bg-indigo-50 p-4 rounded-md border border-indigo-100"
                >
                    <label class="block font-bold text-indigo-900 text-sm mb-1">
                        Pilih Sheet Sumber dari Excel ({{ selectedSheetName }})
                    </label>
                    <select
                        v-model="selectedSourceSheet"
                        @change="loadHeaders(selectedSourceSheet)"
                        class="w-full border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 rounded-md mt-1 text-sm"
                    >
                        <option value="">-- pilih sheet sumber --</option>
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

            <div
                class="bg-white p-6 rounded-lg shadow-sm border border-gray-100"
            >
                <div class="mb-4 pb-2 border-b">
                    <h2 class="font-bold text-gray-700">Konfigurasi Kolom</h2>
                    <p class="text-xs text-gray-500">
                        Hubungkan kolom target (kiri) dengan kolom di excel
                        (kanan).
                    </p>
                </div>

                <div v-if="sheetFields[selectedSheetName]">
                    <div
                        v-for="field in sheetFields[selectedSheetName]"
                        :key="field"
                        class="flex items-center mb-4 last:mb-0"
                    >
                        <label class="w-1/3 font-medium text-sm text-gray-600">
                            {{ field }}
                        </label>
                        <select
                            v-model="mapping[field]"
                            :disabled="!selectedSourceSheet"
                            class="w-2/3 border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 p-2 rounded-md text-sm disabled:bg-gray-50 disabled:text-gray-400"
                        >
                            <option value="">-- biarkan kosong --</option>
                            <option v-for="h in headers" :key="h" :value="h">
                                {{ h }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="selectedSheetName === 'Kode Produk JIM'"
                    class="mt-10 p-5 bg-orange-50 border border-orange-200 rounded-lg shadow-inner"
                >
                    <div class="md:flex justify-between items-center mb-4">
                        <div class="mb-3 md:mb-0">
                            <h3
                                class="font-bold text-orange-900 flex items-center gap-2"
                            >
                                🔍 Verifikasi Master Data
                            </h3>
                            <p class="text-xs text-orange-700 mt-1">
                                Klik matching untuk memvalidasi Nama Barang
                                dengan Master Database sebelum export.
                            </p>
                        </div>
                        <button
                            @click="compareWithMaster"
                            :disabled="isComparing || !selectedSourceSheet"
                            class="bg-orange-600 text-white px-6 py-2.5 rounded-md font-bold hover:bg-orange-700 transition-colors disabled:bg-gray-300 shadow-sm flex items-center gap-2"
                        >
                            <span v-if="isComparing">⌛</span>
                            {{
                                isComparing
                                    ? "Memproses..."
                                    : "⚡ Jalankan Matching"
                            }}
                        </button>
                    </div>

                    <div
                        v-if="comparisonResults.length > 0"
                        class="mt-4 border border-orange-200 rounded-md bg-white max-h-72 overflow-y-auto"
                    >
                        <table class="w-full text-xs text-left">
                            <thead class="bg-orange-100 sticky top-0">
                                <tr>
                                    <th class="p-3 border-b border-orange-200">
                                        Nama Excel
                                    </th>
                                    <th class="p-3 border-b border-orange-200">
                                        Match Database
                                    </th>
                                    <th
                                        class="p-3 border-b border-orange-200 text-center"
                                    >
                                        Skor
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="(item, idx) in comparisonResults"
                                    :key="idx"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3 text-gray-700">
                                        {{ item["Agent Nama"] }}
                                    </td>
                                    <td class="p-3">
                                        <div
                                            class="font-bold"
                                            :class="
                                                item['Status'] === 'MATCH'
                                                    ? 'text-blue-700'
                                                    : 'text-red-500'
                                            "
                                        >
                                            {{ item["Master Nama"] }}
                                        </div>
                                        <div
                                            class="text-[10px] text-gray-400 font-mono"
                                        >
                                            {{ item["Item Code"] }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            class="px-2 py-1 rounded-full font-bold"
                                            :class="
                                                item['Score'] >= 90
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-yellow-100 text-yellow-700'
                                            "
                                        >
                                            {{ item["Score"] }}%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-8 space-y-3">
                    <button
                        @click="saveMapping"
                        class="w-full bg-blue-600 text-white py-3.5 rounded-lg font-bold hover:bg-blue-700 transition-all flex items-center justify-center gap-2"
                    >
                        💾 Simpan Mapping Tab {{ selectedSheetName }}
                    </button>

                    <button
                        @click="processExport"
                        class="w-full bg-green-600 text-white py-4 rounded-lg font-black text-lg hover:bg-green-700 transition-all shadow-lg flex items-center justify-center gap-2"
                    >
                        📊 GENERATE & EXPORT (3 SHEET)
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
