<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { Head, useForm, router, Link } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    users: Object,
    roles: Array,
    regionals: Array,
    all_supervisors: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const toast = useToast();
const showDeleteModal = ref(false);
const userToDelete = ref(null);

// State untuk Assign Supervisor
const showAssignModal = ref(false);
const selectedAgent = ref(null);
const searchQuery = ref("");
const supervisors = ref([]); // Nanti diisi dari props atau fetch

const form = useForm({
    name: "",
    email: "",
    password: "",
    role: "",
    regional_id: "",
});

const formAssign = useForm({
    supervisor_ids: [],
});

// Fungsi membuka modal
const openAssignModal = (user) => {
    selectedAgent.value = user;
    searchQuery.value = "";
    
    // Mapping ID supervisor yang sudah ada ke form
    if (user.supervisors) {
        formAssign.supervisor_ids = user.supervisors.map((s) => s.id);
    } else {
        formAssign.supervisor_ids = [];
    }
    
    showAssignModal.value = true;
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    selectedAgent.value = null;
    formAssign.reset();
};

// Logic pencarian supervisor (dari props.users atau data supervisor khusus)
const filteredSupervisors = computed(() => {
    // Ambil dari list supervisor lengkap yang baru kita buat di controller
    const list = props.all_supervisors || [];

    if (!searchQuery.value) return list;

    const query = searchQuery.value.toLowerCase();
    return list.filter((user) => {
        return (
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query)
        );
    });
});

const submitAssign = () => {
    formAssign.post(route("users.assign-supervisor", selectedAgent.value.id), {
        onSuccess: () => {
            toast.success("Supervisor berhasil ditugaskan!");
            closeAssignModal();
        },
        onError: () => toast.error("Gagal menyimpan data."),
    });
};

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (user) => {
    form.clearErrors();
    isEditing.value = true;
    editId.value = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = "";
    form.role = user.roles && user.roles.length > 0 ? user.roles[0].name : "";
    form.regional_id = user.regional_id || "";
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(
                isEditing.value
                    ? "Data User diperbarui!"
                    : "User berhasil ditambah!",
            );
            showModal.value = false;
            form.reset();
        },
        onError: () =>
            toast.error("Terjadi kesalahan. Cek kembali inputan Anda."),
    };

    if (isEditing.value) {
        form.put(route("users.update", editId.value), options);
    } else {
        form.post(route("users.store"), options);
    }
};

const confirmDelete = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("users.destroy", userToDelete.value.id), {
        onSuccess: () => {
            toast.success("User berhasil dihapus!");
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Daftar Agent" />

    <AuthenticatedLayout>
        <template #header>Daftar Agent</template>

        <div class="space-y-6 p-4">
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <!-- <p>
                    Role Anda saat ini:
                    {{ $page.props.auth.user.roles?.[0] || "Tidak ada role" }}
                </p> -->

                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Daftar List Agent
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ users.total || 0 }} agent aktif
                    </p>
                </div>
                <!-- <button
                    @click="openAddModal"
                    class="bg-blue-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-blue-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah User
                </button> -->
            </div>

            <div
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <table class="w-full text-sm text-center">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Nama Agent</th>
                            <th
                                v-if="
                                    $page.props.auth.user.roles?.includes(
                                        'Administrator',
                                    )
                                "
                                class="px-6 py-4"
                            >
                                Role
                            </th>
                            <th class="px-6 py-4">Regional</th>
                            <th class="px-6 py-4">Supervisor</th>
                            <th class="px-6 py-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ user.name }}
                                </div>
                                <div class="text-xs text-gray-400 font-mono">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td
                                class="px-6 py-4"
                                v-if="
                                    $page.props.auth.user.roles?.includes(
                                        'Administrator',
                                    )
                                "
                            >
                                <span
                                    class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold uppercase"
                                >
                                    {{
                                        user.roles?.length > 0
                                            ? user.roles[0].name
                                            : "No Role"
                                    }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ user.regional ? user.regional.name : "-" }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <div
                                    v-if="
                                        user.supervisors &&
                                        user.supervisors.length > 0
                                    "
                                    class="flex flex-wrap justify-center gap-1"
                                >
                                    <span
                                        v-for="sv in user.supervisors"
                                        :key="sv.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-100 text-indigo-800"
                                    >
                                        {{ sv.name }}
                                    </span>
                                </div>
                                <span
                                    v-else
                                    class="text-gray-400 italic text-xs"
                                    >Belum ada</span
                                >
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="openEditModal(user)"
                                        class="bg-blue-50 text-blue-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="openAssignModal(user)"
                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-md font-bold text-[11px] hover:bg-green-600 hover:text-white transition-colors duration-200"
                                        title="Tugaskan Supervisor"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                            />
                                        </svg>
                                        Set Supervisor
                                    </button>
                                    <!-- <button
                                        v-if="
                                            $page.props.auth.user.roles?.includes(
                                                'Administrator',
                                            )
                                        "
                                        @click="confirmDelete(agent)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition"
                                    >
                                        Hapus
                                    </button> -->
                                    <!-- <button
                                        @click="confirmDelete(user)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition"
                                    >
                                        Hapus
                                    </button> -->
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="px-6 py-10 text-gray-500">
                                Data tidak ditemukan
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between"
                >
                    <div class="text-xs text-gray-500">
                        Menampilkan {{ users.from || 0 }} sampai
                        {{ users.to || 0 }} dari {{ users.total }} data
                    </div>

                    <div class="flex gap-1">
                        <template v-for="(link, k) in users.links" :key="k">
                            <div
                                v-if="link.url === null"
                                class="px-3 py-1 text-[11px] text-gray-400 border rounded-md"
                                v-html="link.label"
                            />
                            <Link
                                v-else
                                :href="link.url"
                                class="px-3 py-1 text-[11px] border rounded-md transition-colors duration-200"
                                :class="{
                                    'bg-blue-600 text-white border-blue-600':
                                        link.active,
                                    'bg-white text-gray-700 hover:bg-gray-100':
                                        !link.active,
                                }"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-lg font-bold text-gray-900 border-b pb-3">
                    {{ isEditing ? "Edit Data User" : "Tambah User Baru" }}
                </h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="name" value="Nama Lengkap" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                        />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <!-- <div>
                        <InputLabel
                            for="password"
                            :value="
                                isEditing
                                    ? 'Password (Kosongkan jika tidak ingin ganti)'
                                    : 'Password'
                            "
                        />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            :required="!isEditing"
                        />
                        <InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div> -->

                    <div>
                        <InputLabel for="regional" value="Regional" />
                        <select
                            id="regional"
                            v-model="form.regional_id"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                        >
                            <option value="">Pilih Regional</option>
                            <option
                                v-for="reg in regionals"
                                :key="reg.id"
                                :value="reg.id"
                            >
                                {{ reg.name }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.regional_id"
                            class="mt-1"
                        />
                    </div>

                    <!-- <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="role" value="Role Akses" />
                            <select
                                id="role"
                                v-model="form.role"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                required
                            >
                                <option value="">Pilih Role</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.role"
                                class="mt-1"
                            />
                        </div>
                    </div> -->
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton @click="showModal = false"
                        >Batal</SecondaryButton
                    >
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ isEditing ? "Simpan Perubahan" : "Tambah User" }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <div
                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4"
                >
                    <span class="text-red-600 font-bold text-xl">!</span>
                </div>
                <h2 class="text-lg font-bold text-gray-900">
                    Konfirmasi Hapus
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus user
                    <b>{{ userToDelete?.name }}</b
                    >? Data yang sudah dihapus tidak bisa dikembalikan.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <SecondaryButton @click="showDeleteModal = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="executeDelete"
                        >Ya, Hapus User</DangerButton
                    >
                </div>
            </div>
        </Modal>

        <Modal :show="showAssignModal" @close="closeAssignModal">
            <div class="p-6">
                <div
                    class="flex justify-between items-center border-b pb-3 mb-4"
                >
                    <h2 class="text-lg font-bold text-gray-800">
                        Set Supervisor untuk:
                        <span class="text-indigo-600">{{
                            selectedAgent?.name
                        }}</span>
                    </h2>
                    <button
                        @click="closeAssignModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama supervisor..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <div
                    class="max-h-60 overflow-y-auto border rounded-md p-2 bg-gray-50"
                >
                    <div
                        v-if="filteredSupervisors.length > 0"
                        class="space-y-1"
                    >
                        <label
                            v-for="sv in filteredSupervisors"
                            :key="sv.id"
                            class="flex items-center p-2 hover:bg-white hover:shadow-sm rounded-md cursor-pointer transition"
                        >
                            <input
                                type="checkbox"
                                :value="sv.id"
                                v-model="formAssign.supervisor_ids"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                            <div class="ml-3">
                                <p class="text-sm font-semibold text-gray-700">
                                    {{ sv.name }}
                                </p>
                                <p class="text-[10px] text-gray-500">
                                    {{ sv.email }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <div
                        v-else
                        class="text-center py-4 text-gray-500 text-sm italic"
                    >
                        Data tidak ditemukan.
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="closeAssignModal"
                        class="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-800 transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="submitAssign"
                        :disabled="formAssign.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-bold shadow-md hover:bg-indigo-700 disabled:opacity-50 transition"
                    >
                        {{
                            formAssign.processing
                                ? "Menyimpan..."
                                : "Simpan Perubahan"
                        }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

// SCRIPT AGETN LAMAMAMAMAMAMMA
<!-- <script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

// defineProps({ agents: Array, supervisors: Array });
const props = defineProps({
    agents: Array,
    supervisors: Array,
    regionals: Array, // Pastikan semua prop yang dibutuhkan terdaftar
});

const showModal = ref(false);
const isEditing = ref(false); // Penanda apakah sedang edit atau tambah
const editId = ref(null); // Menyimpan ID yang sedang diedit
const toast = useToast();
const fileInput = ref(null);
const showDeleteModal = ref(false);
const agentToDelete = ref(null);

const showAssignModal = ref(false);
const selectedAgent = ref(null);
const searchQuery = ref("");

const formAssign = useForm({
    supervisor_ids: [],
});

// Fungsi filter untuk pencarian supervisor di dalam modal
const filteredSupervisors = computed(() => {
    // Pastikan props.supervisors ada nilainya sebelum di-filter
    if (!props.supervisors) return [];

    return props.supervisors.filter((sv) =>
        sv.name.toLowerCase().includes(searchQuery.value.toLowerCase()),
    );
});

const openAssignModal = (agent) => {
    selectedAgent.value = agent;

    // Ambil ID supervisor yang sudah ada (asumsi agent punya relasi supervisors)
    formAssign.supervisor_ids = agent.supervisors
        ? agent.supervisors.map((sv) => sv.id)
        : [];

    showAssignModal.value = true;
};

// const openAssignModal = (agent) => {
//     selectedAgent.value = agent;
//     // Pre-fill checkbox dengan supervisor yang sudah ter-assign sebelumnya
//     formAssign.supervisor_ids = agent.supervisors
//         ? agent.supervisors.map((sv) => sv.id)
//         : [];
//     showAssignModal.value = true;
// };

const closeAssignModal = () => {
    showAssignModal.value = false;
    selectedAgent.value = null;
    searchQuery.value = "";
    formAssign.reset();
};

const submitAssign = () => {
    formAssign.post(route("agents.assign-supervisor", selectedAgent.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAssignModal();
            toast.success("Penugasan Supervisor berhasil diperbarui!");
        },
        onError: () => {
            toast.error("Terjadi kesalahan, silakan coba lagi.");
        },
    });
};

const form = useForm({
    code: "",
    name: "",
    regional_id: "",
});

// Fungsi untuk buka modal TAMBAH
const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset(); // Mengosongkan input code dan name
    form.clearErrors(); // Menghapus pesan error merah jika ada
    showModal.value = true;
};

// Fungsi untuk buka modal EDIT
const openEditModal = (agent) => {
    form.clearErrors();
    isEditing.value = true;
    editId.value = agent.id;
    form.code = agent.code;
    form.name = agent.name;
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            // 1. Notifikasi Sukses
            const message = isEditing.value
                ? "Data Agent berhasil diperbarui!"
                : "Agent baru berhasil ditambahkan!";

            toast.success(message);

            // 2. Reset & Tutup Modal
            // Jika kamu punya fungsi closeModal(), sebaiknya panggil itu saja
            showModal.value = false;
            form.reset();
            form.clearErrors();
        },
        onError: () => {
            // Notifikasi jika ada error validasi atau server
            toast.error("Terjadi kesalahan. Silakan cek kembali inputan Anda.");
        },
        onFinish: () => {
            // Opsional: stop loading jika ada
        },
    };

    if (isEditing.value) {
        form.put(route("agents.update", editId.value), options);
    } else {
        form.post(route("agents.store"), options);
    }
};

const confirmDelete = (agent) => {
    agentToDelete.value = agent;
    showDeleteModal.value = true;
};

// Fungsi eksekusi hapus
const executeDelete = () => {
    router.delete(route("agents.destroy", agentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Agent berhasil dihapus!");
            showDeleteModal.value = false;
            agentToDelete.value = null;
        },
        onError: () => {
            toast.error("Gagal menghapus agent. Mungkin data masih digunakan?");
        },
    });
};
</script>

<template>
    <Head title="Daftar Agents" />

    <AuthenticatedLayout>
        <template #header>Daftar Agents</template>

        <div class="space-y-6">
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Data Master Agent
                    </h3>
                    <p class="text-xs text-gray-500">
                        Total: {{ agents.length }} agent
                    </p>
                </div>
                <button
                    @click="openAddModal"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md font-bold text-sm hover:bg-indigo-700 transition flex items-center gap-2"
                >
                    <span>+</span> Tambah Agent
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
                            <th class="px-6 py-4 text-center">Kode</th>
                            <th class="px-6 py-4 text-center">Nama Agent</th>
                            <th class="px-6 py-4 text-center">Regional</th>
                            <th class="px-6 py-4 text-center">Supervisor</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="agent in agents"
                            :key="agent.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td
                                class="px-6 py-4 font-mono text-indigo-600 font-bold text-center"
                            >
                                {{ agent.code }}
                            </td>

                            <td class="px-6 py-4 text-gray-800 text-center">
                                {{ agent.name }}
                            </td>
                            <td class="px-6 py-4 text-gray-800 text-center">
                                {{ agent.regional?.name || "-" }}
                            </td>

                            <td class="px-6 py-4 text-gray-800 text-center">
                                <div
                                    v-if="
                                        agent.supervisors &&
                                        agent.supervisors.length > 0
                                    "
                                    class="flex flex-wrap justify-center gap-1"
                                >
                                    <span
                                        v-for="sv in agent.supervisors"
                                        :key="sv.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800"
                                    >
                                        {{ sv.name }}
                                    </span>
                                </div>
                                <span
                                    v-else
                                    class="text-gray-400 italic text-sm"
                                >
                                    Belum ada supervisor
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-center items-center gap-2"
                                >
                                    <button
                                        @click="openEditModal(agent)"
                                        class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-md font-bold text-[11px] hover:bg-blue-600 hover:text-white transition-colors duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
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
                                        @click="confirmDelete(agent)"
                                        class="inline-flex items-center px-3 py-1 bg-red-50 text-red-700 border border-red-200 rounded-md font-bold text-[11px] hover:bg-red-600 hover:text-white transition-colors duration-200"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
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

                                    <button
                                        @click="openAssignModal(agent)"
                                        class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-md font-bold text-[11px] hover:bg-green-600 hover:text-white transition-colors duration-200"
                                        title="Tugaskan Supervisor"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3 mr-1"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                            />
                                        </svg>
                                        Set Supervisor
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="agents.length === 0">
                            <td
                                colspan="3"
                                class="px-6 py-10 text-center text-gray-400 italic"
                            >
                                Belum ada data agent. Klik tombol "Tambah Agent"
                                untuk mengisi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showModal" :closeable="false" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">
                    {{ isEditing ? "Edit Agent" : "Tambah Agent" }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >Kode Agent</label
                        >
                        <input
                            v-model="form.code"
                            type="text"
                            class="w-full border-gray-300 rounded-md shadow-sm"
                            required
                        />
                        <div
                            v-if="form.errors.code"
                            class="text-red-500 text-xs mt-1"
                        >
                            {{ form.errors.code }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                            >Nama Agent</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border-gray-300 rounded-md shadow-sm"
                            required
                        />
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold text-gray-500 mb-1 uppercase"
                        >
                            Regional / Wilayah
                        </label>
                        <select
                            v-model="form.regional_id"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                            required
                        >
                            <option value="" disabled selected>
                                -- Pilih Regional --
                            </option>
                            <option
                                v-for="reg in $page.props.regionals"
                                :key="reg.id"
                                :value="reg.id"
                            >
                                {{ reg.name }}
                            </option>
                        </select>
                        <div
                            v-if="form.errors.regional_id"
                            class="text-red-500 text-xs mt-1"
                        >
                            {{ form.errors.regional_id }}
                        </div>
                        <div
                            v-if="form.errors.regional_id"
                            class="text-red-500 text-xs mt-1"
                        >
                            {{ form.errors.regional_id }}
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="text-gray-500 text-sm font-bold px-4"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-indigo-600 text-white px-4 py-2 rounded font-bold text-sm disabled:opacity-50"
                        >
                            {{ isEditing ? "Update Data" : "Simpan" }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal
            :show="showDeleteModal"
            :closeable="false"
            @close="showDeleteModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-800 border-b pb-3">
                    Konfirmasi Hapus
                </h2>

                <div class="mt-4">
                    <p class="text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus agent:
                    </p>
                    <p
                        class="text-md font-bold text-indigo-600 mt-1"
                        v-if="agentToDelete"
                    >
                        {{ agentToDelete.code }} - {{ agentToDelete.name }}
                    </p>
                    <p class="mt-3 text-[11px] text-red-500 italic">
                        *Tindakan ini tidak dapat dibatalkan dan mungkin
                        berpengaruh pada data laporan terkait.
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
                        Ya, Hapus Agent
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="showAssignModal" @close="closeAssignModal">
            <div class="p-6">
                <div
                    class="flex justify-between items-center border-b pb-3 mb-4"
                >
                    <h2 class="text-lg font-bold text-gray-800">
                        Assign Supervisor untuk:
                        <span class="text-indigo-600">{{
                            selectedAgent?.name
                        }}</span>
                    </h2>
                    <button
                        @click="closeAssignModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama supervisor..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <div
                    class="max-h-60 overflow-y-auto border rounded-md p-2 bg-gray-50"
                >
                    <div
                        v-if="filteredSupervisors.length > 0"
                        class="space-y-1"
                    >
                        <label
                            v-for="sv in filteredSupervisors"
                            :key="sv.id"
                            class="flex items-center p-2 hover:bg-white hover:shadow-sm rounded-md cursor-pointer transition"
                        >
                            <input
                                type="checkbox"
                                :value="sv.id"
                                v-model="formAssign.supervisor_ids"
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                            />
                            <div class="ml-3">
                                <p class="text-sm font-semibold text-gray-700">
                                    {{ sv.name }}
                                </p>
                                <p class="text-[10px] text-gray-500">
                                    {{ sv.email }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <div
                        v-else
                        class="text-center py-4 text-gray-500 text-sm italic"
                    >
                        Supervisor tidak ditemukan.
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="closeAssignModal"
                        class="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-800 transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="submitAssign"
                        :disabled="formAssign.processing"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-bold shadow-md hover:bg-indigo-700 disabled:opacity-50 transition"
                    >
                        {{
                            formAssign.processing
                                ? "Menyimpan..."
                                : "Simpan Perubahan"
                        }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template> -->
