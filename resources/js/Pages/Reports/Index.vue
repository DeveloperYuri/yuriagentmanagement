<script setup>
import { ref, computed, onMounted } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";
import axios from "axios";

const page = usePage();
const showScanModal = ref(false);
const showResetModal = ref(false);
const reportToReset = ref(null);

const scanExcel = (report) => {
    router.get(route("python.mapping"), {
        file_path: report.file_path,
    });
};

const userRole = computed(() => {
    // Ambil string role pertama, jika tidak ada default ke string kosong
    const role =
        page.props.auth.user?.role || page.props.auth.user?.roles?.[0] || "";
    return role.toLowerCase();
});

const canManageReport = computed(() => {
    // Daftar role yang dilarang
    const restrictedRoles = [
        "general manager",
        "general manager (gm)",
        "regional sales manager (rsm)",
        "supervisor",
        "Supervisor",
    ];
    return !restrictedRoles.includes(userRole.value);
});

const canSeeAgentColumn = computed(() => {
    const userRoles = page.props.auth.user?.roles || [];
    // Ubah ke huruf kecil semua agar pengecekan lebih aman (case-insensitive)
    const primaryRole = userRoles[0]?.toLowerCase();

    if (!primaryRole) return false;

    // Daftar role yang diizinkan melihat kolom Nama Agent
    const allowedRoles = [
        "administrator",
        "general manager",
        "general manager (gm)",
        "regional sales manager (rsm)",
        "supervisor",
        "Supervisor", // RSM sekarang bisa melihat kolom ini juga
    ];

    return allowedRoles.includes(primaryRole);
});

const props = defineProps({
    reports: Array,
    // agents: Array,
});

const showModal = ref(false);
const toast = useToast();
const fileInput = ref(null);
const showDeleteModal = ref(false);
const reportToDelete = ref(null);
const isEditing = ref(false); // Tambahkan ini
const editingId = ref(null); // Tambahkan ini

const closeModal = () => {
    showModal.value = false;
    isEditing.value = false; // Reset state edit
    editingId.value = null;
    form.reset();
    form.clearErrors();
    if (fileInput.value) fileInput.value.value = null;
};

const openEditModal = (report) => {
    isEditing.value = true;
    editingId.value = report.id;

    // Isi form dengan data yang akan diedit
    // form.agent_id = report.agent_id;
    form.month = report.month;
    form.year = report.year;
    form.file = null; // File dikosongkan kecuali user ingin ganti

    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        // Mode Update Menggunakan router.post manual
        router.post(
            route("reports.update", editingId.value),
            {
                _method: "put", // Masukkan method spoofing di sini
                agent_id: form.agent_id,
                month: form.month,
                year: form.year,
                file: form.file, // File input
            },
            {
                forceFormData: true, // Wajib untuk upload file
                onSuccess: () => {
                    toast.success("Laporan berhasil diperbarui!");
                    closeModal();
                },
            },
        );
    } else {
        // Mode Create tetap gunakan form.post
        form.post(route("reports.store"), {
            forceFormData: true,
            onSuccess: () => {
                toast.success("Laporan berhasil diupload!");
                closeModal();
            },
        });
    }
};

const form = useForm({
    // agent_id: "",
    month: new Date().getMonth() + 1, // Default bulan sekarang
    year: new Date().getFullYear(), // Default tahun sekarang
    file: null,
});

const months = [
    "Januari",
    "Februari",
    "Maret",
    "April",
    "Mei",
    "Juni",
    "Juli",
    "Agustus",
    "September",
    "Oktober",
    "November",
    "Desember",
];

const downloadFile = (id) => {
    window.location.href = route("reports.download", id);
};

// ... import yang sudah ada ...

const confirmDelete = (report) => {
    console.log("Tombol hapus diklik untuk report ID:", report.id);
    reportToDelete.value = report;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("reports.destroy", reportToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Laporan berhasil dihapus!");
            showDeleteModal.value = false;
            reportToDelete.value = null;
        },
        onError: () => {
            toast.error("Gagal menghapus laporan.");
        },
    });
};

onMounted(() => {
    console.log("Data Reports yang diterima:", props.reports);
});

const goToMapping = (report) => {
    if (!report.file_path) {
        toast.error("File tidak ditemukan");
        return;
    }

    router.get(
        route("import.mapping") +
            `?filePath=${report.file_path}&agent_id=${report.user_id}`,
    );

    // router.get(route("import.mapping"), {
    //     filePath: report.file_path,
    //     agent_id: report.user_id, // 🔥 INI KUNCI NYA
    // });
};

const goToMultiMapping2 = (report) => {
    router.get(route("exportMappingPage"), {
        filePath: report.file_path,
        agent_id: report.user_id,
        report_id: report.id,
    });
};

const confirmReset = (report) => {
    reportToReset.value = report;
    showResetModal.value = true;
};

const executeReset = async () => {
    try {
        const res = await axios.post(
            `/resetmapping/${reportToReset.value.id}`,
            {
                _method: "DELETE",
            },
        );

        console.log("RESET:", res.data);

        showResetModal.value = false;

        toast.success("Mapping berhasil di reset");

        setTimeout(() => {
            window.location.reload();
        }, 1500);
    } catch (error) {
        console.error(error);
        alert("Gagal reset mapping");
    }
};

const goToMultiMapping = (report) => {
    if (!report.file_path) {
        toast.error("File tidak ditemukan");
        return;
    }

    router.get(route("import.mapping.multi"), {
        filePath: report.file_path,
        agent_id: report.user_id,
        report_id: report.id,
    });
};

const filePath = computed(() => page.props.filePath);
const agent_id = computed(() => page.props.agent_id);
const report_id = computed(() => page.props.report_id);
const selectedSheetName = ref("");

const processExport = async (report) => {
    try {
        console.log("REPORT:", report);
        console.log("SHEET:", report.sheet_name);

        const res = await axios.post(
            "/export/process",
            {
                file_path: report.file_path,
                agent_id: report.user_id,
                agent_report_id: report.id,
                sheet: report.sheet_name || "Sheet1",
            },
            {
                responseType: "blob",
            },
        );

        // 🔍 Cek apakah response JSON (error) atau file
        const contentType = res.headers["content-type"];

        if (contentType && contentType.includes("application/json")) {
            // ❌ Ini error dari backend
            const text = await res.data.text();
            const json = JSON.parse(text);

            console.error("ERROR BACKEND:", json);
            alert(json.message || "Terjadi error");

            return;
        }

        // ✅ Ini file Excel → download
        const blob = new Blob([res.data], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        const url = window.URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.download = `RESULT_YURI_${Date.now()}.xlsx`;
        document.body.appendChild(link);
        link.click();

        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        console.log("ERROR FULL:", err);

        // 🔥 Tangkap error dari backend (yang return JSON tapi kebungkus blob)
        if (err.response && err.response.data) {
            try {
                const text = await err.response.data.text();
                const json = JSON.parse(text);

                console.error("ERROR JSON:", json);
                alert(json.message || "Export gagal");
            } catch {
                alert("Terjadi error tidak dikenal");
            }
        } else {
            alert("Server tidak merespon");
        }
    }
};
</script>

<template>
    <Head title="Laporan Agent" />

    <AuthenticatedLayout>
        <template #header>Laporan Bulanan Agent</template>

        <!-- <p>
            Role Anda saat ini:
            {{ $page.props.auth.user.roles?.[0] || "Tidak ada role" }}
        </p> -->

        <div class="space-y-6">
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Daftar Laporan Masuk
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ reports.length }} laporan
                    </p>
                </div>
                <button
                    @click="showModal = true"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-indigo-700 transition"
                >
                    + Upload Laporan
                </button>
            </div>

            <div
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <table class="w-full text-sm">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <!-- <th class="px-6 py-4 text-center">Nama Agent</th> -->
                            <th
                                v-if="canSeeAgentColumn"
                                class="px-6 py-4 text-center"
                            >
                                Nama Agent
                            </th>

                            <th class="px-6 py-4 text-center">Periode</th>
                            <th class="px-6 py-4 text-center">Nama File</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="report in reports"
                            :key="report.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td
                                v-if="canSeeAgentColumn"
                                class="px-6 py-4 text-center font-bold text-gray-700"
                            >
                                {{
                                    report.user?.name ||
                                    report.agent?.user?.name ||
                                    "-"
                                }}
                            </td>

                            <td
                                v-if="
                                    [
                                        'Administrator',
                                        'General Manager (GM)',
                                    ].includes($page.props.auth.user.role)
                                "
                                class="px-6 py-4 text-center"
                            >
                                -
                            </td>

                            <td class="px-6 py-4 text-center">
                                {{ months[report.month - 1] }} {{ report.year }}
                            </td>

                            <td
                                class="px-6 py-4 text-xs text-gray-500 text-center truncate max-w-[200px]"
                            >
                                {{ report.file_name }}
                            </td>

                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-center items-center gap-2"
                                >
                                    <button
                                        @click="downloadFile(report.id)"
                                        class="inline-flex items-center px-3 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-md font-bold text-[11px] hover:bg-indigo-600 hover:text-white transition-all duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                            />
                                        </svg>
                                        Download
                                    </button>

                                    <!-- <button
                                        @click="goToMapping(report)"
                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-md font-bold text-[11px] hover:bg-green-600 hover:text-white transition-all duration-200"
                                    >
                                        Import
                                    </button> -->

                                    <button
                                        @click="confirmReset(report)"
                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-all duration-200"
                                    >
                                        Reset Mapping
                                    </button>

                                    <!-- <button
                                        @click="scanExcel(report)"
                                        class="inline-flex items-center px-3 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-md font-bold text-[11px] hover:bg-yellow-600 hover:text-white transition-all duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                            />
                                        </svg>
                                        Set Mapping
                                    </button> -->

                                    <!-- <button
                                        @click="goToMultiMapping(report)"
                                        class="inline-flex items-center px-3 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-md font-bold text-[11px] hover:bg-purple-600 hover:text-white transition-all duration-200"
                                    >
                                        Multi Sheet Mapping
                                    </button> -->

                                    <button
                                        @click="goToMultiMapping2(report)"
                                        class="inline-flex items-center px-3 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-md font-bold text-[11px] hover:bg-purple-600 hover:text-white transition-all duration-200"
                                    >
                                        Multi Sheet Mapping V2
                                    </button>

                                    <button
                                        @click="processExport(report)"
                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-md font-bold text-[11px] hover:bg-green-600 hover:text-white transition-all duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                            />
                                        </svg>

                                        Export Excel
                                    </button>

                                    <template v-if="canManageReport">
                                        <button
                                            @click="openEditModal(report)"
                                            class="inline-flex items-center px-3 py-1 bg-orange-50 text-orange-700 border border-orange-200 rounded-md font-bold text-[11px] hover:bg-orange-600 hover:text-white transition-all duration-200"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-3.5 w-3.5 mr-1"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                />
                                            </svg>
                                            Edit
                                        </button>

                                        <button
                                            @click="confirmDelete(report)"
                                            class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-all duration-200"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-3.5 w-3.5 mr-1"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                />
                                            </svg>
                                            Hapus
                                        </button>
                                    </template>

                                    <!-- <button
                                        @click="openEditModal(report)"
                                        class="inline-flex items-center px-3 py-1 bg-orange-50 text-orange-700 border border-orange-200 rounded-md font-bold text-[11px] hover:bg-orange-600 hover:text-white transition-all duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                            />
                                        </svg>
                                        Edit
                                    </button>

                                    <button
                                        @click="confirmDelete(report)"
                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-all duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3.5 w-3.5 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                        Hapus
                                    </button> -->
                                </div>
                            </td>
                        </tr>

                        <tr v-if="reports.length === 0">
                            <td
                                colspan="4"
                                class="px-6 py-10 text-center text-gray-400 italic"
                            >
                                Belum ada laporan yang diupload.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" :closeable="false" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-800 border-b pb-2">
                    {{
                        isEditing ? "Edit Laporan Agent" : "Upload Laporan Baru"
                    }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                        >
                            Pilih Agent
                        </label>
                        <select
                            v-model="form.agent_id"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            required
                        >
                            <option value="" disabled>-- Pilih Agent --</option>
                            <option
                                v-for="agent in agents"
                                :key="agent.id"
                                :value="agent.id"
                            >
                                {{ agent.code }} - {{ agent.name }}
                            </option>
                        </select>
                        <div
                            v-if="form.errors.agent_id"
                            class="text-red-500 text-[10px] mt-1 font-bold italic"
                        >
                            {{ form.errors.agent_id }}
                        </div>
                    </div> -->

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >
                                Bulan
                            </label>
                            <select
                                v-model="form.month"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option
                                    v-for="(m, index) in months"
                                    :key="index"
                                    :value="index + 1"
                                >
                                    {{ m }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >
                                Tahun
                            </label>
                            <input
                                v-model="form.year"
                                type="number"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                        >
                            File Laporan (Excel/PDF)
                            <span
                                v-if="isEditing"
                                class="text-orange-500 normal-case font-normal ml-1"
                            >
                                *Kosongkan jika tidak ingin ganti file
                            </span>
                        </label>
                        <input
                            ref="fileInput"
                            type="file"
                            @input="form.file = $event.target.files[0]"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-all"
                            accept=".xlsx,.xls,.pdf"
                            :required="!isEditing"
                        />

                        <div
                            v-if="form.errors.file"
                            class="text-red-500 text-[10px] mt-1 font-bold italic"
                        >
                            {{ form.errors.file }}
                        </div>

                        <progress
                            v-if="form.progress"
                            :value="form.progress.percentage"
                            max="100"
                            class="w-full h-2 mt-2 rounded overflow-hidden"
                        >
                            {{ form.progress.percentage }}%
                        </progress>
                    </div>

                    <div class="flex justify-end gap-2 mt-6 pt-4 border-t">
                        <button
                            type="button"
                            @click="closeModal"
                            class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            :class="[
                                'px-4 py-2 rounded font-bold text-sm shadow-sm transition disabled:opacity-50',
                                isEditing
                                    ? 'bg-orange-600 hover:bg-orange-700 text-white'
                                    : 'bg-indigo-600 hover:bg-indigo-700 text-white',
                            ]"
                        >
                            <span v-if="form.processing">Mengirim...</span>
                            <span v-else>{{
                                isEditing
                                    ? "Simpan Perubahan"
                                    : "Upload Laporan"
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showDeleteModal"
            :closeable="true"
            @close="showDeleteModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">
                    Konfirmasi Hapus Laporan
                </h2>

                <div class="mt-4" v-if="reportToDelete">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus laporan berikut?
                    </p>

                    <div
                        class="mt-3 p-3 bg-red-50 rounded-lg border border-red-100"
                    >
                        <table class="text-sm">
                            <tr>
                                <td class="pr-4 text-gray-500">Periode</td>
                                <td class="font-bold uppercase">
                                    : {{ months[reportToDelete.month - 1] }}
                                    {{ reportToDelete.year }}
                                </td>
                            </tr>
                            <tr>
                                <td class="pr-4 text-gray-500">File</td>
                                <td class="font-bold text-xs">
                                    : {{ reportToDelete.file_name }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="mt-4 text-[11px] text-red-500 italic">
                        *File di server akan dihapus secara permanen dan tidak
                        bisa dikembalikan.
                    </p>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        class="bg-red-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-red-700 shadow-sm transition"
                    >
                        Ya, Hapus Laporan
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showScanModal"
            @close="showScanModal = false"
            :maxWidth="'7xl'"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">Preview Data Excel</h2>

                <div class="overflow-auto max-h-[70vh] border rounded">
                    <table class="min-w-full text-xs border">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th
                                    v-for="header in scannedHeaders"
                                    :key="header"
                                    class="px-3 py-2 border text-left"
                                >
                                    {{ header }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in scannedData"
                                :key="index"
                                class="hover:bg-gray-50"
                            >
                                <td
                                    v-for="header in scannedHeaders"
                                    :key="header"
                                    class="px-3 py-2 border"
                                >
                                    {{ row[header] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        @click="showScanModal = false"
                        class="px-4 py-2 bg-gray-500 text-white rounded"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showResetModal"
            :closeable="true"
            @close="showResetModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">
                    Konfirmasi Reset Mapping
                </h2>

                <div class="mt-4" v-if="reportToReset">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin mereset mapping untuk laporan
                        agent berikut?
                    </p>

                    <div
                        class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-100"
                    >
                        <table class="text-sm">
                            <tr>
                                <td class="pr-4 text-gray-500">Nama Agent</td>
                                <td class="font-bold">
                                    :
                                    {{
                                        reportToReset.user?.name ||
                                        reportToReset.agent?.user?.name ||
                                        "-"
                                    }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="mt-4 text-[11px] text-yellow-600 italic">
                        *Mapping akan dihapus dan perlu dilakukan setting ulang.
                    </p>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        @click="showResetModal = false"
                        class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeReset"
                        class="bg-yellow-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-yellow-700 shadow-sm transition"
                    >
                        Ya, Reset Mapping
                    </button>
                </div>
            </div>
        </Modal>

        <!-- <Modal
            :show="showDeleteModal"
            :closeable="false"
            @close="showDeleteModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">
                    Konfirmasi Hapus Laporan
                </h2>

                <div class="mt-4" v-if="reportToDelete">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus laporan berikut?
                    </p>
                    <div
                        class="mt-3 p-3 bg-red-50 rounded-lg border border-red-100"
                    >
                        <table class="text-sm">
                            <tr>
                                <td class="pr-4 text-gray-500">Agent</td>
                                <td class="font-bold">
                                    : {{ reportToDelete.agent.name }}
                                </td>
                            </tr>
                            <tr>
                                <td class="pr-4 text-gray-500">Periode</td>
                                <td class="font-bold">
                                    : {{ months[reportToDelete.month - 1] }}
                                    {{ reportToDelete.year }}
                                </td>
                            </tr>
                            <tr>
                                <td class="pr-4 text-gray-500">File</td>
                                <td class="font-bold text-xs">
                                    : {{ reportToDelete.file_name }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <p class="mt-4 text-[11px] text-red-500 italic">
                        *File di server akan dihapus secara permanen dan tidak
                        bisa dikembalikan.
                    </p>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="text-gray-500 text-sm font-bold px-4 py-2 hover:bg-gray-100 rounded-md transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeDelete"
                        class="bg-red-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-red-700 shadow-sm transition"
                    >
                        Ya, Hapus Laporan
                    </button>
                </div>
            </div>
        </Modal> -->
    </AuthenticatedLayout>
</template>
