<template>
    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white shadow rounded-lg p-6 mb-6 text-center">
                <h1 class="text-2xl font-bold mb-2">
                    🚀 YURI AGENT MANAGEMENT
                </h1>
                <input type="file" @change="handleFileChange" class="mt-4" />
                <button
                    @click="scanExcel"
                    :disabled="isLoading"
                    class="bg-blue-600 text-white px-6 py-2 rounded ml-2"
                >
                    {{ isLoading ? "Scanning..." : "1. SCAN EXCEL" }}
                </button>
            </div>

            <div class="mb-6 p-4 bg-gray-100 border rounded-lg">
                <h3 class="font-bold mb-2">Preset Mapping</h3>
                <div class="flex gap-2 mb-4">
                    <input
                        v-model="presetName"
                        type="text"
                        placeholder="Nama Preset"
                        class="border p-2 rounded flex-1"
                    />
                    <button
                        @click="saveCurrentMapping"
                        class="bg-yellow-500 text-white px-4 py-2 rounded"
                    >
                        Simpan Mapping
                    </button>
                    <button
                        @click="fetchPresets"
                        class="bg-blue-500 text-white px-4 py-2 rounded"
                    >
                        🔄 Ambil List
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="ps in savedMappings"
                        :key="ps.name"
                        @click="applyPreset(ps.data)"
                        class="bg-white border border-blue-300 text-blue-700 px-3 py-1 rounded text-xs"
                    >
                        📄 {{ ps.name }}
                    </button>
                </div>
            </div>

            <div
                v-if="headers.length > 0"
                class="bg-white shadow p-6 rounded-lg"
            >
                <div
                    v-for="field in targetFields"
                    :key="field"
                    class="flex items-center mb-3 border-b pb-2"
                >
                    <label class="w-1/3 text-sm font-semibold">{{
                        field
                    }}</label>
                    <select
                        v-model="form.mapping[field]"
                        class="w-2/3 p-2 border rounded"
                    >
                        <option value="">-- Kosongkan --</option>
                        <option v-for="h in headers" :key="h" :value="h">
                            {{ h }}
                        </option>
                    </select>
                </div>
                <button
                    @click="processExport"
                    :disabled="isProcessing"
                    class="w-full bg-green-600 text-white py-4 rounded-xl font-bold mt-4"
                >
                    {{ isProcessing ? "PROSESING..." : "📊 DOWNLOAD EXCEL" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useForm } from "@inertiajs/vue3";
import axios from "axios";

const file = ref(null);
const headers = ref([]);
const isLoading = ref(false);
const isProcessing = ref(false);
const savedMappings = ref([]);
const presetName = ref("");

// HANYA SATU SUMBER DATA MAPPING
const form = useForm({
    mapping: {},
});

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
    "Total Invoice Value",
];

const handleFileChange = (e) => {
    file.value = e.target.files[0];
};

const scanExcel = async () => {
    isLoading.value = true;
    const fd = new FormData();
    fd.append("file", file.value);
    try {
        const res = await axios.post("/python/scan", fd);
        headers.value = res.data.headers;
        // Reset mapping saat scan file baru
        form.mapping = {};
    } finally {
        isLoading.value = false;
    }
};

const fetchPresets = async () => {
    const res = await axios.get("/python/get-mappings");
    savedMappings.value = res.data;
};

const saveCurrentMapping = async () => {
    if (!presetName.value) return alert("Nama preset wajib diisi!");
    await axios.post("/python/save-mapping", {
        name: presetName.value,
        mapping: form.mapping,
    });
    alert("Berhasil!");
    fetchPresets();
};

const applyPreset = (presetData) => {
    form.mapping = { ...presetData };
};

const processExport = async () => {
    isProcessing.value = true;
    const fd = new FormData();
    fd.append("file", file.value);
    fd.append("mapping", JSON.stringify(form.mapping));
    try {
        const res = await axios.post("/python/process", fd, {
            responseType: "blob",
        });
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement("a");
        link.href = url;
        link.setAttribute("download", "RESULT_YURI.xlsx");
        link.click();
    } finally {
        isProcessing.value = false;
    }
};

onMounted(fetchPresets);
</script>
