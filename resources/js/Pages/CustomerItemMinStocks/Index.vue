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

import { Head, useForm, router } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";

const props = defineProps({
    minimumStocks: Object,
    filters: Object,
    users: Array,
    items: Array,
});

const toast = useToast();

const search = ref(props.filters?.search || "");

const showModal = ref(false);
const showDeleteModal = ref(false);

const isEditing = ref(false);
const editId = ref(null);

const selectedData = ref(null);
const customerSearch = ref("");
const itemSearch = ref("");

const selectItem = () => {
    const selected = props.items.find(
        (i) => `${i.item_code} - ${i.item_name}` === itemSearch.value,
    );

    form.item_id = selected ? selected.id : "";
};

const selectCustomer = () => {
    const selected = props.users.find(
        (u) => `${u.kode_user} - ${u.name}` === customerSearch.value,
    );

    form.user_id = selected ? selected.id : "";
};

const filteredItems = computed(() => {
    if (!itemSearch.value) return [];

    return props.items
        .filter((item) => {
            const text = `${item.item_code} ${item.item_name}`.toLowerCase();

            return text.includes(itemSearch.value.toLowerCase());
        })
        .slice(0, 20);
});

const chooseItem = (item) => {
    itemSearch.value = `${item.item_code} - ${item.item_name}`;

    form.item_id = item.id;
};

const list = computed(
    () => props.minimumStocks?.data || props.minimumStocks || [],
);

const form = useForm({
    user_id: "",
    item_id: "",
    minimum_stock: "",
});

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;

    form.reset();
    form.clearErrors();

    showModal.value = true;
};

const openEditModal = (row) => {
    isEditing.value = true;
    editId.value = row.id;

    form.user_id = row.user_id;
    form.item_id = row.item_id;
    form.minimum_stock = row.minimum_stock;

    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            toast.success(
                isEditing.value
                    ? "Minimum stock diupdate!"
                    : "Minimum stock ditambahkan!",
            );

            showModal.value = false;

            form.reset();

            customerSearch.value = "";

            itemSearch.value = "";
        },

        onError: () => {
            toast.error("Terjadi kesalahan.");
        },
    };

    isEditing.value
        ? form.put(
              route("customer-item-min-stocks.update", editId.value),
              options,
          )
        : form.post(route("customer-item-min-stocks.store"), options);
};

const confirmDelete = (row) => {
    selectedData.value = row;

    showDeleteModal.value = true;
};

const executeDelete = () => {
    router.delete(
        route("customer-item-min-stocks.destroy", selectedData.value.id),
        {
            onSuccess: () => {
                toast.success("Data berhasil dihapus!");

                showDeleteModal.value = false;
            },
        },
    );
};

const doSearch = () => {
    router.get(
        route("customer-item-min-stocks.index"),
        {
            search: search.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const goToPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Customer Minimum Stock" />

    <AuthenticatedLayout>
        <template #header> Customer Minimum Stock </template>

        <div class="space-y-6 p-4">
            <!-- HEADER -->
            <div
                class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border"
            >
                <div>
                    <h3 class="text-lg font-bold text-gray-800">
                        Customer Minimum Stock
                    </h3>

                    <p class="text-xs text-gray-500">
                        Total:
                        {{ minimumStocks.total || list.length }} data
                    </p>
                </div>

                <button
                    @click="openAddModal"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-md text-sm font-bold hover:bg-indigo-700"
                >
                    + Tambah Data
                </button>
            </div>

            <!-- SEARCH -->
            <div class="bg-white p-4 border rounded-lg">
                <input
                    v-model="search"
                    @input="doSearch"
                    type="text"
                    placeholder="Search customer or item..."
                    class="w-full border rounded-md px-3 py-2 text-sm"
                />
            </div>

            <!-- TABLE -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <table class="w-full text-sm">
                    <thead
                        class="bg-gray-50 text-gray-600 text-xs uppercase border-b"
                    >
                        <tr>
                            <th class="px-6 py-4 text-left">Customer</th>

                            <th class="px-6 py-4 text-left">Customer Code</th>

                            <th class="px-6 py-4 text-left">Item</th>

                            <th class="px-6 py-4 text-left">Item Code</th>

                            <th class="px-6 py-4 text-center">Minimum Stock</th>

                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr v-for="row in list" :key="row.id">
                            <td class="px-6 py-4">
                                {{ row.user?.name }}
                            </td>

                            <td
                                class="px-6 py-4 font-mono text-indigo-600 font-bold"
                            >
                                {{ row.user?.kode_user }}
                            </td>

                            <td class="px-6 py-4">
                                {{ row.item?.item_name }}
                            </td>

                            <td
                                class="px-6 py-4 font-mono text-green-600 font-bold"
                            >
                                {{ row.item?.item_code }}
                            </td>

                            <td class="px-6 py-4 text-center">
                                {{ row.minimum_stock }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        @click="openEditModal(row)"
                                        class="bg-blue-50 text-blue-700 px-3 py-1 rounded text-xs font-bold hover:bg-blue-600 hover:text-white"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        @click="confirmDelete(row)"
                                        class="bg-red-50 text-red-700 px-3 py-1 rounded text-xs font-bold hover:bg-red-600 hover:text-white"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="list.length === 0">
                            <td
                                colspan="6"
                                class="py-10 text-center text-gray-400 italic"
                            >
                                Belum ada data
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <div
                    v-if="minimumStocks.links"
                    class="flex justify-between items-center p-4 border-t"
                >
                    <div class="text-xs text-gray-500">
                        Showing
                        {{ minimumStocks.from }}
                        -
                        {{ minimumStocks.to }}
                        of
                        {{ minimumStocks.total }}
                    </div>

                    <div class="flex gap-1">
                        <template
                            v-for="(link, i) in minimumStocks.links"
                            :key="i"
                        >
                            <button
                                v-if="link.url"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                                class="px-3 py-1 text-xs rounded border"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-white hover:bg-gray-100'
                                "
                            />

                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1 text-xs text-gray-400"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold mb-5">
                    {{
                        isEditing
                            ? "Edit Minimum Stock"
                            : "Tambah Minimum Stock"
                    }}
                </h2>

                <!-- CUSTOMER -->
                <div class="mb-4">
                    <InputLabel value="Customer" />

                    <input
                        v-model="customerSearch"
                        list="customer-list"
                        class="w-full border-gray-300 rounded-md mt-1"
                        placeholder="Cari customer..."
                        @change="selectCustomer"
                    />

                    <datalist id="customer-list">
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="`${user.kode_user} - ${user.name}`"
                        />
                    </datalist>

                    <InputError :message="form.errors.user_id" />
                </div>

                <!-- ITEM -->
                <div class="mb-4 relative">
                    <InputLabel value="Item" />

                    <input
                        v-model="itemSearch"
                        type="text"
                        class="w-full border-gray-300 rounded-md mt-1"
                        placeholder="Cari item..."
                    />

                    <div
                        v-if="itemSearch"
                        class="border rounded-md mt-1 max-h-60 overflow-y-auto bg-white shadow absolute w-full z-50"
                    >
                        <div
                            v-for="item in filteredItems"
                            :key="item.id"
                            @click="chooseItem(item)"
                            class="px-3 py-2 hover:bg-indigo-100 cursor-pointer text-sm"
                        >
                            {{ item.item_code }} - {{ item.item_name }}
                        </div>
                    </div>

                    <InputError :message="form.errors.item_id" />
                </div>

                <!-- MIN STOCK -->
                <div class="mb-4">
                    <InputLabel value="Minimum Stock" />

                    <input
                        v-model="form.minimum_stock"
                        type="number"
                        class="w-full mt-1 border-gray-300 rounded-md"
                    />

                    <!-- <TextInput
                        v-model="form.minimum_stock"
                        type="number"
                        class="w-full mt-1"
                    /> -->

                    <InputError :message="form.errors.minimum_stock" />
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <SecondaryButton @click="showModal = false">
                        Batal
                    </SecondaryButton>

                    <PrimaryButton @click="submit">
                        {{ isEditing ? "Update" : "Simpan" }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- DELETE MODAL -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 text-center">
                <h2 class="font-bold">Hapus Data?</h2>

                <p class="text-sm mt-2">
                    {{ selectedData?.item?.item_name }}
                </p>

                <div class="flex justify-center gap-2 mt-4">
                    <SecondaryButton @click="showDeleteModal = false">
                        Batal
                    </SecondaryButton>

                    <DangerButton @click="executeDelete"> Hapus </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
