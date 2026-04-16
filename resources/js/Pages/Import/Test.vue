<script setup>
import { ref } from 'vue';
import axios from 'axios';

// Misal kamu punya props file_path dari hasil upload sebelumnya
const props = defineProps(['report']); 

const excelData = ref([]);
const headers = ref([]);
const loading = ref(false);

const prosesScan = async () => {
    loading.value = true;
    try {
        // Panggil controller lewat Axios
        const response = await axios.post('/excel/scan-raw', {
            file_path: props.report.file_path // Sesuaikan dengan field di DB kamu
        });

        // Simpan hasilnya ke variabel
        headers.value = response.data.headers;
        excelData.value = response.data.data;
        
        alert('Scan Berhasil!');
    } catch (error) {
        console.error(error);
        alert(error.response?.data?.error || 'Gagal scan file');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div>
        <button @click="prosesScan" :disabled="loading">
            {{ loading ? 'Sabar, lagi baca...' : 'Klik buat Scan Data Excel' }}
        </button>

        <div v-if="excelData.length > 0" class="mt-5 overflow-auto">
            <table class="w-full border">
                <thead>
                    <tr>
                        <th v-for="h in headers" :key="h" class="border p-2 bg-gray-100">{{ h }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in excelData" :key="i">
                        <td v-for="h in headers" :key="h" class="border p-2">{{ row[h] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>