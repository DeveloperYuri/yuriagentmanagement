<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { Head, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// =====================
// GLOBAL CONFIG (🔥 FIX UTAMA)
// =====================
axios.defaults.baseURL = "http://localhost:8000";

// =====================
// STATE
// =====================
const file = ref(null);
const headers = ref([]);
const loading = ref(false);
const page = usePage();
const sheets = ref([]);
const selectedSheet = ref(null);
const filePath = ref(usePage().props.filePath || null);
const reportId = page.props.report_id;
const agentId = page.props.agent_id;

const activeTab = ref("jim");

console.log("FILE PATH:", page.props.filePath);

onMounted(async () => {
    if (!filePath.value) {
        console.warn("FILE PATH KOSONG");
        return;
    }

    console.log("SCAN FILE:", filePath.value);

    try {
        const res = await axios.post("/python/scan-file", {
            file_path: filePath.value, // 🔥 beda dengan upload
        });

        console.log("SHEETS:", res.data);

        filePath.value = res.data.file_path;
        sheets.value = res.data.sheets;

        // auto pilih sheet pertama
        if (sheets.value.length > 0) {
            selectedSheet.value = sheets.value[0];
        }
    } catch (err) {
        console.error("SCAN ERROR:", err.response?.data || err);
    }
});

// mapping
const mappingJim = ref({
    "Kode SKU Agent": "",
    "Stock Akhir Agent": "",
    "Nama Produk": "",
});

const mappingInv = ref({
    "Nama Agen": "",
    "Kode Customer": "",
    "Nama Customer": "",
    "Alamat Customer": "",
    "Nomor Telepon/HP Customer": "",
    "Invoice Nomor Agen": "",
    "Tanggal Invoice": "",
    "Tipe Customer": "",
    Sales: "",
    "SKU Kode Agen": "",
    "Nama SKU": "",
    "Qty Terjual (PCS)": "",
    "% Diskon 1 (Reguler)": "",
    "% Diskon 2 (Cash)": "",
    "% Diskon 3 (DC Free)": "",
    "% Diskon 4 (Promo 1)": "",
    "% Diskon 5 (Promo 2)": "",
    "% Diskon 6 (Rp)": "",
    "Quantity Bonus": "",
    Rafraksi: "",
    "Total Invoice Value": "",
});
// =====================
// UPLOAD FILE
// =====================
const handleFile = async (e) => {
    const f = e.target.files[0];
    if (!f) return;

    file.value = f;

    const formData = new FormData();
    formData.append("file", f);

    try {
        console.log("UPLOAD START...");

        const res = await axios.post("/python/scan-file", formData);

        console.log("UPLOAD SUCCESS:", res.data);

        filePath.value = res.data.file_path;
        sheets.value = res.data.sheets;
    } catch (err) {
        console.error("UPLOAD ERROR:", err);
        console.error("DATA:", err.response?.data);
        alert("Upload gagal, cek console");
    }
};

const loadHeaders = async () => {
    if (!selectedSheet.value) return;

    console.log("SHEET DIPILIH:", selectedSheet.value);

    try {
        const res = await axios.post("/python/scan-header", {
            file_path: filePath.value,
            sheet: selectedSheet.value,
        });

        console.log("SERVER RESPONSE:", res.data);

        // Perhatikan strukturnya: res.data.headers.headers
        // Karena di screenshot terlihat nested object
        if (res.data.headers && res.data.headers.headers) {
            headers.value = res.data.headers.headers;
        } else if (res.data.headers && Array.isArray(res.data.headers)) {
            // Backup jika strukturnya flat
            headers.value = res.data.headers;
        } else {
            headers.value = [];
        }
    } catch (err) {
        console.error("HEADER ERROR:", err.response?.data);
        headers.value = [];
    }
};

const saveMapping = async () => {
    if (!filePath.value) return alert("File belum ada");
    if (!selectedSheet.value) return alert("Sheet belum dipilih");

    try {
        const payload = {
            sheet: selectedSheet.value,

            agent_report_id: reportId,
            agent_id: agentId,
            nama_agent: null,

            mapping: {
                jim: mappingJim.value,
                invoice: mappingInv.value,
            },
        };

        const res = await axios.post("/mapping/save", payload);

        console.log("SAVE SUCCESS:", res.data);
        alert("Mapping berhasil disimpan!");
    } catch (err) {
        console.error("SAVE ERROR:", err.response?.data || err);
        alert("Gagal simpan mapping");
    }
};

// =====================
// PROCESS EXPORT
// =====================
const processData = async () => {
    if (!filePath.value) return alert("Upload file dulu");
    if (!selectedSheet.value) return alert("Pilih sheet dulu");

    try {
        loading.value = true;

        console.log("PROCESS START...");

        const payload = {
            file_path: filePath.value,
            sheet_name: selectedSheet.value,
            mapping_jim: mappingJim.value,
            mapping_inv: mappingInv.value,
        };

        const res = await axios.post("/export/process", payload, {
            responseType: "blob",
        });

        // download file
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = "hasil_mapping.xlsx";
        link.click();

        console.log("DOWNLOAD DONE");
    } catch (err) {
        if (err.response?.data instanceof Blob) {
            const reader = new FileReader();
            reader.onload = () => {
                console.error(
                    "DETAIL ERROR DARI SERVER:",
                    JSON.parse(reader.result),
                );
            };
            reader.readAsText(err.response.data);
        }
        alert("Gagal process, cek console untuk detail");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Export Mapping" />

    <AuthenticatedLayout>
        <template #header> Export Mapping Agent </template>

        <div class="p-6 max-w-5xl mx-auto space-y-6">
            <!-- <div class="bg-white p-5 rounded shadow">
                <h2 class="font-bold mb-3">📂 Upload Laporan Agent</h2>
                <input
                    type="file"
                    @change="handleFile"
                    class="border p-2 w-full"
                />
            </div> -->

            <div class="bg-white p-5 rounded shadow">
                <h2 class="font-bold mb-3">📄 Pilih Sheet</h2>
                <select
                    v-model="selectedSheet"
                    @change="loadHeaders"
                    class="border p-2 w-full"
                >
                    <option value="">-- pilih sheet --</option>
                    <option v-for="s in sheets" :key="s" :value="s">
                        {{ s }}
                    </option>
                </select>
            </div>

            <div class="flex border-b border-gray-200">
                <button
                    @click="activeTab = 'jim'"
                    :class="[
                        'py-2 px-6 font-bold',
                        activeTab === 'jim'
                            ? 'border-b-2 border-blue-600 text-blue-600'
                            : 'text-gray-500',
                    ]"
                >
                    🧠 Mapping Kode JIM
                </button>
                <button
                    @click="activeTab = 'invoice'"
                    :class="[
                        'py-2 px-6 font-bold',
                        activeTab === 'invoice'
                            ? 'border-b-2 border-blue-600 text-blue-600'
                            : 'text-gray-500',
                    ]"
                >
                    📑 Mapping Invoice
                </button>
            </div>

            <div v-if="activeTab === 'jim'" class="bg-white p-5 rounded shadow">
                <h2 class="font-bold mb-4 text-blue-600 text-lg">
                    🧠 Konfigurasi JIM
                </h2>
                <div v-for="(v, key) in mappingJim" :key="key" class="mb-3">
                    <label
                        class="block text-sm mb-1 font-medium text-gray-700"
                        >{{ key }}</label
                    >
                    <select
                        v-model="mappingJim[key]"
                        class="border p-2 w-full rounded focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">-- pilih kolom --</option>
                        <option v-for="h in headers" :key="h" :value="h">
                            {{ h }}
                        </option>
                    </select>
                </div>
            </div>

            <div
                v-if="activeTab === 'invoice'"
                class="bg-white p-5 rounded shadow"
            >
                <h2
                    class="font-bold text-blue-600 text-lg mb-4 text-center border-b pb-2"
                >
                    📑 Konfigurasi Invoice
                </h2>

                <div class="columns-1 md:columns-2 gap-12 space-y-4">
                    <div
                        v-for="(v, key) in mappingInv"
                        :key="key"
                        class="break-inside-avoid-column mb-4 border-b border-gray-50 pb-2"
                    >
                        <label
                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-tight mb-1"
                        >
                            {{ key }}
                        </label>
                        <select
                            v-model="mappingInv[key]"
                            class="border p-2 w-full text-sm rounded bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 transition-all"
                        >
                            <option value="">-- pilih kolom excel --</option>
                            <option v-for="h in headers" :key="h" :value="h">
                                {{ h }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <button
                @click="saveMapping"
                class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold shadow"
            >
                💾 Save Mapping
            </button>

            <button
                @click="processData"
                :disabled="loading"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded font-bold transition duration-200 shadow-lg disabled:bg-gray-400"
            >
                <span v-if="loading">⏳ Processing Data... Please wait...</span>
                <span v-else>🚀 Process & Export All Sheets</span>
            </button>
        </div>
    </AuthenticatedLayout>
</template>
