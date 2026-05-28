<script setup>
import { ref } from "vue";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";
import axios from "axios";

// 🔥 Multiselect
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const props = defineProps({
    mappings: Object,
    masters: Array,
    filters: Object,
});

const toast = useToast();

const showModal = ref(false);
const showDeleteModal = ref(false);
const search = ref(props.filters.search || "");
const isEdit = ref(false);
const editId = ref(null);
const mappingToDelete = ref(null);
const showImportModal = ref(false);
const selectedFile = ref(null);

const form = useForm({
    agent_sku: "",
    item_code: "",
    item_name: "",
    item_per_box: "",
    item_group: "",
});

const handleFileChange = (e) => {
    selectedFile.value = e.target.files[0];
};

// 🔥 options master
const masterOptions = (props.masters || []).map((m) => ({
    label: `${m.item_code} - ${m.item_name}`,
    value: m.item_code,

    item_name: m.item_name,

    item_per_box: m.item_per_box,

    item_group: m.item_group,
}));

const normalizeText = (text) => {
    return (text || "").toLowerCase().replace(/[^a-z0-9]/g, "");
};

const customSearch = (option, search) => {
    const optionText = normalizeText(option.label);

    const searchText = normalizeText(search);

    return optionText.includes(searchText);
};

// SEARCH
const doSearch = () => {
    router.get(
        route("agent-item-mappings.index"),
        {
            search: search.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

// OPEN MODAL
const openModal = () => {
    form.reset();

    isEdit.value = false;

    editId.value = null;

    showModal.value = true;
};

// EDIT
const editMapping = (item) => {
    isEdit.value = true;

    editId.value = item.id;

    form.agent_sku = item.agent_sku;

    form.item_code = item.item_code;

    form.item_name = item.item_name;

    form.item_per_box = item.item_per_box;

    form.item_group = item.item_group;

    showModal.value = true;
};

// DELETE
const deleteMapping = (item) => {
    mappingToDelete.value = item;

    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(
        route("agent-item-mappings.destroy", mappingToDelete.value.id),
        {
            onSuccess: () => {
                toast.success("Mapping berhasil dihapus!");

                showDeleteModal.value = false;

                mappingToDelete.value = null;
            },

            onError: () => {
                toast.error("Gagal menghapus mapping");
            },
        },
    );
};

// SELECT ITEM
const selectItem = (value) => {
    const selected = masterOptions.find((m) => m.value === value);

    if (!selected) {
        return;
    }

    form.item_name = selected.item_name;

    form.item_per_box = selected.item_per_box;

    form.item_group = selected.item_group;
};

const importExcel = (e) => {
    const file = e.target.files[0];

    if (!file) return;

    const formData = new FormData();

    formData.append("file", file);

    axios
        .post(route("agent-item-mappings.import"), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })
        .then(() => {
            toast.success("Import berhasil!");

            showImportModal.value = false;

            router.reload();
        })
        .catch(() => {
            toast.error("Import gagal!");
        });
};

const submitImport = () => {
    if (!selectedFile.value) {
        toast.error("Pilih file terlebih dahulu!");

        return;
    }

    const formData = new FormData();

    formData.append("file", selectedFile.value);

    axios
        .post(route("agent-item-mappings.import"), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })
        .then(() => {
            toast.success("Import berhasil!");

            showImportModal.value = false;

            selectedFile.value = null;

            router.reload();
        })
        .catch((error) => {
            console.log("FULL ERROR:", error);

            console.log("RESPONSE:", error.response);

            console.log("DATA:", error.response?.data);

            alert(JSON.stringify(error.response?.data));

            toast.error(error.response?.data?.message || "Import gagal!");
        });
};

// SUBMIT
const submit = () => {
    // EDIT
    if (isEdit.value) {
        form.put(route("agent-item-mappings.update", editId.value), {
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

    // CREATE
    form.post(route("agent-item-mappings.store"), {
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
</script>

<template>
    <Head title="Agent Item Mapping" />

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
                    {{ mappingToDelete.agent_sku }}
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
        <template #header> Agent Item Mapping </template>

        <div class="p-4 space-y-6">
            <!-- HEADER -->
            <div
                class="flex justify-between items-center bg-white p-4 border rounded-lg"
            >
                <h2 class="font-bold text-gray-800">Agent Item Mapping</h2>

                <div class="flex gap-2">
                    <!-- IMPORT -->
                    <input
                        type="file"
                        ref="excelFile"
                        class="hidden"
                        @change="importExcel"
                        accept=".xlsx,.xls"
                    />

                    <button
                        @click="showImportModal = true"
                        class="bg-green-600 text-white px-4 py-2 rounded-md text-sm font-bold hover:bg-green-700"
                    >
                        Import Excel
                    </button>

                    <!-- TAMBAH -->
                    <button
                        @click="openModal"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-bold hover:bg-indigo-700"
                    >
                        + Tambah Mapping
                    </button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <!-- SEARCH -->
                <div class="p-4 border-b">
                    <input
                        v-model="search"
                        @input="doSearch"
                        type="text"
                        placeholder="Search Agent SKU..."
                        class="w-full border rounded-md px-3 py-2 text-sm"
                    />
                </div>

                <table class="w-full text-sm text-center">
                    <thead
                        class="bg-gray-50 text-gray-600 uppercase font-bold text-[10px] border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Agent SKU</th>

                            <th class="px-6 py-4 text-left">Item Code</th>

                            <th class="px-6 py-4 text-left">Item Name</th>

                            <th class="px-6 py-4 text-center">Item / Box</th>

                            <th class="px-6 py-4 text-left">Item Group</th>

                            <th class="px-6 py-4 text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="item in mappings.data"
                            :key="item.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-6 py-4 text-left">
                                <div class="font-bold text-gray-800">
                                    {{ item.agent_sku }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-left">
                                {{ item.item_code }}
                            </td>

                            <td class="px-6 py-4 text-left">
                                {{ item.item_name }}
                            </td>

                            <td class="px-6 py-4 text-center">
                                {{ item.item_per_box }}
                            </td>

                            <td class="px-6 py-4 text-left">
                                {{ item.item_group }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="editMapping(item)"
                                        class="bg-yellow-500 text-white px-3 py-1 rounded text-xs"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="deleteMapping(item)"
                                        class="bg-red-600 text-white px-3 py-1 rounded text-xs"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="mappings.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-10 text-gray-500 italic"
                            >
                                Data mapping belum ada
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <div class="flex justify-end mt-4 space-x-2 p-4">
                    <button
                        v-for="link in mappings.links"
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
                <h2 class="text-lg font-bold mb-4">
                    {{ isEdit ? "Edit Mapping" : "Tambah Mapping" }}
                </h2>

                <!-- AGENT SKU -->
                <div class="mb-4">
                    <InputLabel value="Agent SKU" />

                    <TextInput v-model="form.agent_sku" class="w-full mt-1" />

                    <div
                        v-if="form.errors.agent_sku"
                        class="text-red-500 text-sm mt-1"
                    >
                        {{ form.errors.agent_sku }}
                    </div>
                </div>

                <!-- ITEM CODE -->
                <div class="mb-4">
                    <InputLabel value="Pilih Master Produk" />

                    <Multiselect
                        v-model="form.item_code"
                        :options="masterOptions"
                        label="label"
                        track-by="value"
                        valueProp="value"
                        placeholder="Cari produk..."
                        searchable
                        :searchFilter="customSearch"
                        class="mt-1"
                        @change="selectItem"
                    />

                    <div
                        v-if="form.errors.item_code"
                        class="text-red-500 text-sm mt-1"
                    >
                        {{ form.errors.item_code }}
                    </div>
                </div>

                <!-- ITEM NAME -->
                <div class="mb-4">
                    <InputLabel value="Item Name" />

                    <TextInput
                        v-model="form.item_name"
                        class="w-full mt-1 bg-gray-100"
                        readonly
                    />
                </div>

                <!-- ITEM / BOX -->
                <div class="mb-4">
                    <InputLabel value="Item / Box" />

                    <TextInput
                        v-model="form.item_per_box"
                        class="w-full mt-1 bg-gray-100"
                        readonly
                    />
                </div>

                <!-- ITEM GROUP -->
                <div class="mb-4">
                    <InputLabel value="Item Group" />

                    <TextInput
                        v-model="form.item_group"
                        class="w-full mt-1 bg-gray-100"
                        readonly
                    />
                </div>

                <!-- ACTION -->
                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submit"> Simpan </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- IMPORT MODAL -->
        <Modal :show="showImportModal" @close="showImportModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-4">Import Excel Mapping</h2>

                <!-- FILE -->
                <div class="mb-4">
                    <InputLabel value="Pilih File Excel" />

                    <input
                        type="file"
                        ref="excelFile"
                        @change="handleFileChange"
                        accept=".xlsx,.xls"
                        class="w-full border rounded-md p-2 mt-1"
                    />
                </div>

                <!-- FILE NAME -->
                <div
                    v-if="selectedFile"
                    class="text-sm text-gray-600 bg-gray-100 p-2 rounded"
                >
                    {{ selectedFile.name }}
                </div>

                <!-- ACTION -->
                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showImportModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton
                        @click="submitImport"
                        :disabled="!selectedFile"
                    >
                        Import
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
