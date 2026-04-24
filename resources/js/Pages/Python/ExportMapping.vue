<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { Head, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "vue-toastification";

const page = usePage();
const toast = useToast();

const filePath = page.props.filePath;

// =====================
// STATE
// =====================
const selectedSourceSheet = ref("");
const sheets = ref([]);
const headers = ref([]);
const mapping = ref({});

// =====================
// SET MAPPING (ANTI BUG)
// =====================
const setMapping = (key, value) => {
    mapping.value = {
        ...mapping.value,
        [key]: value,
    };
};

// =====================
// FIELD TARGET
// =====================
const sheetFields = ["Kode SKU Agent", "Stock Akhir Agent"];

// =====================
// LOAD SHEET
// =====================
onMounted(async () => {
    try {
        const res = await axios.post("/python/mapping-exportscan", {
            file_path: filePath,
        });

        sheets.value = res.data.sheets;
    } catch (err) {
        toast.error("Gagal scan file");
    }
});

// =====================
// LOAD HEADER
// =====================
const loadHeaders = (sheetName) => {
    const found = sheets.value.find((s) => s.sheet === sheetName);
    headers.value = found ? found.headers : [];

    console.log("HEADERS:", headers.value); // 🔥 debug
};

// =====================
// EXPORT
// =====================
const exportData = async () => {
    if (!selectedSourceSheet.value) {
        alert("❌ Pilih sheet dulu");
        return;
    }

    if (!mapping.value["Kode SKU Agent"]) {
        alert("❌ Pilih kolom KODE SKU AGENT");
        return;
    }

    if (!mapping.value["Stock Akhir Agent"]) {
        alert("❌ Pilih kolom STOCK AKHIR AGENT");
        return;
    }

    try {
        const payload = {
            file_path: filePath,
            sheet: selectedSourceSheet.value,
            mapping: { ...mapping.value }, // 🔥 penting
        };

        console.log("MAPPING FRONTEND:", payload);

        const res = await axios.post("/python/mapping-exportscan", payload, {
            responseType: "blob",
        });

        const blob = new Blob([res.data]);
        const url = window.URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.download = "EXPORT_MAPPING.xlsx";
        link.click();
    } catch (err) {
        console.error("❌ FULL ERROR:", err);

        if (err.response && err.response.data) {
            const reader = new FileReader();

            reader.onload = () => {
                try {
                    const json = JSON.parse(reader.result);
                    console.log("🔥 BACKEND ERROR:", json);

                    alert("ERROR:\n" + json.error);
                } catch {
                    console.log("RAW:", reader.result);
                    alert("Error tidak terbaca");
                }
            };

            reader.readAsText(err.response.data);
        }
    }
};
</script>

<template>
    <Head title="Mapping Kode Produk JIM" />

    <AuthenticatedLayout>
        <template #header> Mapping Kode Produk JIM </template>

        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <!-- HEADER -->
            <div class="bg-white p-6 rounded-lg shadow border">
                <h1 class="text-xl font-bold mb-4">
                    🧠 Mapping Kode Produk JIM
                </h1>

                <!-- SELECT SHEET -->
                <div class="bg-indigo-50 p-4 rounded border">
                    <label class="text-sm font-bold block mb-1">
                        Pilih Sheet Excel
                    </label>

                    <select
                        v-model="selectedSourceSheet"
                        @change="loadHeaders(selectedSourceSheet)"
                        class="w-full border p-2 rounded"
                    >
                        <option value="">-- pilih sheet --</option>
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
            <div class="bg-white p-6 rounded-lg shadow border">
                <h2 class="font-bold mb-4">Konfigurasi Kolom</h2>

                <div
                    v-for="field in sheetFields"
                    :key="field"
                    class="flex items-center mb-3"
                >
                    <label class="w-1/3 text-sm">{{ field }}</label>

                    <select
                        :value="mapping[field]"
                        @change="(e) => setMapping(field, e.target.value)"
                        class="w-2/3 border p-2 rounded"
                    >
                        <option value="">-- pilih kolom --</option>
                        <option v-for="h in headers" :key="h" :value="h">
                            {{ h }}
                        </option>
                    </select>
                </div>

                <!-- BUTTON -->
                <button
                    @click="exportData"
                    class="w-full mt-6 bg-green-600 text-white py-3 rounded font-bold"
                >
                    🚀 EXPORT
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>