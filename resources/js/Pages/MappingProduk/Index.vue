<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

// 🔥 Multiselect
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const props = defineProps({
    masters: Array,
    aliases: Object,
});

const toast = useToast();
const showModal = ref(false);
const search = ref("");
const isEdit = ref(false);
const editId = ref(null);
const showDeleteModal = ref(false);
const mappingToDelete = ref(null);

const doSearch = () => {
    router.get(
        route("mappingproduk.index"),
        { search: search.value },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const form = useForm({
    agent_name: "",
    master_name: "",
});

// 🔥 mapping ke options multiselect
const masterOptions = props.masters.map((m) => ({
    label: m.item_name,
    value: m.item_name,
}));

// const filteredAliases = computed(() => {
//     if (!search.value) {
//         return props.aliases.data;
//     }

//     return props.aliases.data.filter((a) =>
//         a.agent_name?.toLowerCase().includes(search.value.toLowerCase()),
//     );
// });

// buka modal
const openModal = () => {
    form.reset();

    isEdit.value = false;
    editId.value = null;

    showModal.value = true;
};
// const openModal = () => {
//     form.reset();
//     showModal.value = true;
// };

const editMapping = (item) => {
    isEdit.value = true;
    editId.value = item.id;

    form.agent_name = item.agent_name;
    form.master_name = item.master_name;

    showModal.value = true;
};

const deleteMapping = (item) => {
    mappingToDelete.value = item;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(route("mapping-produk.destroy", mappingToDelete.value.id), {
        onSuccess: () => {
            toast.success("Mapping berhasil dihapus!");

            showDeleteModal.value = false;
            mappingToDelete.value = null;
        },

        onError: () => {
            toast.error("Gagal menghapus mapping");
        },
    });
};

// const deleteMapping = (id) => {
//     if (!confirm("Yakin hapus mapping ini?")) {
//         return;
//     }

//     router.delete(route("mapping-produk.destroy", id), {
//         onSuccess: () => {
//             toast.success("Mapping berhasil dihapus!");
//         },

//         onError: () => {
//             toast.error("Gagal menghapus mapping");
//         },
//     });
// };

// submit
const submit = () => {
    // MODE EDIT
    if (isEdit.value) {
        form.put(route("mapping-produk.update", editId.value), {
            onSuccess: () => {
                toast.success("Mapping berhasil diupdate!");

                showModal.value = false;

                form.reset();

                isEdit.value = false;
                editId.value = null;
            },

            onError: () => {
                toast.error("Gagal update mapping");
            },
        });

        return;
    }

    // MODE TAMBAH
    form.post(route("mapping-produk.store"), {
        onSuccess: () => {
            toast.success("Mapping berhasil disimpan!");

            showModal.value = false;

            form.reset();
        },

        onError: () => {
            toast.error("Gagal menyimpan mapping");
        },
    });
};
// const submit = () => {
//     form.post(route("mapping-produk.store"), {
//         onSuccess: () => {
//             toast.success("Mapping berhasil disimpan!");
//             showModal.value = false;
//             form.reset();
//         },
//         onError: () => {
//             toast.error("Gagal menyimpan mapping");
//         },
//     });
// };
</script>

<template>
    <Head title="Mapping Produk" />

    <!-- DELETE MODAL -->
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
                    Apakah Anda yakin ingin menghapus mapping:
                </p>

                <p
                    class="text-md font-bold text-indigo-600 mt-1"
                    v-if="mappingToDelete"
                >
                    {{ mappingToDelete.agent_name }}
                </p>

                <p class="mt-3 text-[11px] text-red-500 italic">
                    *Data mapping yang dihapus tidak dapat dikembalikan.
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
                    Ya, Hapus
                </button>
            </div>
        </div>
    </Modal>

    <AuthenticatedLayout>
        <template #header>Mapping Produk</template>

        <div class="p-4 space-y-6">
            <!-- HEADER -->
            <div
                class="flex justify-between items-center bg-white p-4 border rounded-lg"
            >
                <h2 class="font-bold text-gray-800">Mapping Produk</h2>

                <button
                    @click="openModal"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-bold"
                >
                    + Tambah Mapping
                </button>
            </div>

            <!-- TABLE -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <!-- SEARCH -->
                <div class="p-4 border-b">
                    <input
                        v-model="search"
                        @input="doSearch"
                        type="text"
                        placeholder="Search Agent Name..."
                        class="w-full border rounded-md px-3 py-2 text-sm"
                    />
                </div>

                <table class="w-full text-sm text-center">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Agent Name</th>
                            <th class="px-6 py-4 text-left">Master Name</th>
                            <th class="px-6 py-4 text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="a in aliases.data"
                            :key="a.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ a.agent_name }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-left">
                                {{ a.master_name }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="editMapping(a)"
                                        class="bg-yellow-500 text-white px-3 py-1 rounded text-xs"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="deleteMapping(a)"
                                        class="bg-red-600 text-white px-3 py-1 rounded text-xs"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="aliases.data.length === 0">
                            <td
                                colspan="2"
                                class="px-6 py-10 text-gray-500 italic"
                            >
                                Data mapping belum ada
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex justify-end mt-4 space-x-2">
                    <button
                        v-for="link in aliases.links"
                        :key="link.label"
                        v-html="link.label"
                        @click="$inertia.visit(link.url)"
                        :disabled="!link.url"
                        class="px-3 py-1 border rounded text-sm"
                        :class="{ 'bg-gray-200': link.active }"
                    />
                </div>
            </div>
        </div>

        <!-- MODAL -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">Tambah Mapping Produk</h2>

                <!-- Nama Agent -->
                <div class="mb-3">
                    <InputLabel value="Nama Produk Agent" />
                    <TextInput v-model="form.agent_name" class="w-full mt-1" />
                </div>

                <!-- 🔥 Multiselect -->
                <div>
                    <InputLabel value="Pilih Master Produk" />

                    <Multiselect
                        v-model="form.master_name"
                        :options="masterOptions"
                        label="label"
                        track-by="value"
                        :reduce="(option) => option.value"
                        placeholder="Cari produk..."
                        searchable
                        class="mt-1"
                    />
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submit"> Simpan </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
