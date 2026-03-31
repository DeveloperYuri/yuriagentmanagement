<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { Head } from "@inertiajs/vue3";
import {
    HomeIcon,
    UsersIcon,
    ArrowUpTrayIcon,
    Cog6ToothIcon,
    Bars3Icon,
    XMarkIcon,
    ChevronRightIcon,
} from "@heroicons/vue/24/outline";

const isSidebarOpen = ref(true); // Sidebar terbuka default di desktop
const isMobileMenuOpen = ref(false); // Sidebar mobile

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-gray-100 flex">
        <aside
            :class="[
                isSidebarOpen ? 'w-64' : 'w-20',
                isMobileMenuOpen
                    ? 'translate-x-0'
                    : '-translate-x-full lg:translate-x-0',
            ]"
            class="fixed inset-y-0 left-0 z-50 bg-[#343a40] text-gray-300 transition-all duration-300 ease-in-out shadow-xl lg:static lg:inset-0"
        >
            <div
                class="h-16 flex items-center px-4 bg-[#3b444b] border-b border-gray-700 overflow-hidden whitespace-nowrap"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-3"
                >
                    <ApplicationLogo
                        :show-text="isSidebarOpen"
                        class="h-8 fill-current text-indigo-400 shrink-0"
                    />
                    <!-- <ApplicationLogo
                        class="h-8 w-8 fill-current text-indigo-400 shrink-0"
                    /> -->
                    <!-- <span
                        v-show="isSidebarOpen"
                        class="font-bold text-xl text-white tracking-wider uppercase"
                        >Yuri
                        <span class="font-light text-gray-400">ERP</span></span
                    > -->
                </Link>
            </div>

            <div
                class="px-4 py-5 border-b border-gray-700 flex items-center gap-3 overflow-hidden whitespace-nowrap bg-[#3b444b]/30"
            >
                <div class="shrink-0 relative">
                    <img
                        :src="`https://ui-avatars.com/api/?name=${$page.props.auth.user.name}&background=6366f1&color=fff`"
                        class="h-10 w-10 rounded-full border-2 border-gray-600 shadow-sm"
                        alt="User"
                    />
                    <span
                        class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full bg-green-500 ring-2 ring-[#343a40]"
                    ></span>
                </div>
                <div
                    v-show="isSidebarOpen"
                    class="transition-opacity duration-300"
                >
                    <p class="text-sm font-bold text-white truncate w-32">
                        {{ $page.props.auth.user.name }}
                    </p>
                    <p
                        class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold italic"
                    >
                        {{ $page.props.auth.user?.roles?.[0] || "No Role" }}
                    </p>
                    <!-- <p
                        class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold italic"
                    >
                        Administrator
                    </p> -->
                </div>
            </div>

            <nav class="mt-4 px-2 space-y-1">
                <Link
                    v-if="
                        $page.props.auth.user?.roles?.some((role) =>
                            ['admin', 'manager'].includes(role),
                        )
                    "
                    :href="route('dashboard')"
                    :class="
                        route().current('dashboard')
                            ? 'bg-indigo-600 text-white shadow-lg'
                            : 'hover:bg-gray-700 hover:text-white'
                    "
                    class="group flex items-center gap-3 px-3 py-3 rounded-md transition-all duration-200"
                >
                    <HomeIcon class="h-6 w-6 shrink-0" />
                    <span v-show="isSidebarOpen" class="text-sm font-medium"
                        >Dashboard</span
                    >
                </Link>

                <Link
                    href="#"
                    class="group flex items-center justify-between px-3 py-3 rounded-md hover:bg-gray-700 hover:text-white transition-all"
                >
                    <div class="flex items-center gap-3">
                        <UsersIcon class="h-6 w-6 shrink-0" />
                        <span v-show="isSidebarOpen" class="text-sm font-medium"
                            >Manage Agents</span
                        >
                    </div>
                    <ChevronRightIcon
                        v-show="isSidebarOpen"
                        class="h-4 w-4 text-gray-500 group-hover:text-white transition-transform"
                    />
                </Link>

                <Link
                    href="#"
                    class="group flex items-center gap-3 px-3 py-3 rounded-md hover:bg-gray-700 hover:text-white transition-all"
                >
                    <ArrowUpTrayIcon class="h-6 w-6 shrink-0" />
                    <span v-show="isSidebarOpen" class="text-sm font-medium"
                        >Import Excel</span
                    >
                </Link>

                <div class="pt-4 pb-2">
                    <p
                        v-show="isSidebarOpen"
                        class="px-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2"
                    >
                        Sistem
                    </p>
                    <hr class="border-gray-700 mx-2" v-show="!isSidebarOpen" />
                </div>

                <Link
                    href="#"
                    class="group flex items-center gap-3 px-3 py-3 rounded-md hover:bg-gray-700 hover:text-white transition-all"
                >
                    <Cog6ToothIcon class="h-6 w-6 shrink-0" />
                    <span v-show="isSidebarOpen" class="text-sm font-medium"
                        >Pengaturan</span
                    >
                </Link>
            </nav>
        </aside>

        <div
            v-if="isMobileMenuOpen"
            @click="isMobileMenuOpen = false"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        ></div>

        <div class="flex-1 flex flex-col min-w-0">
            <header
                class="h-16 bg-white shadow-sm border-b flex items-center justify-between px-4 sticky top-0 z-30"
            >
                <div class="flex items-center gap-4">
                    <button
                        @click="toggleSidebar"
                        class="hidden lg:block p-1 text-gray-500 hover:text-indigo-600 transition"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>
                    <button
                        @click="isMobileMenuOpen = true"
                        class="lg:hidden p-1 text-gray-500"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <h2
                        class="font-black text-gray-800 uppercase tracking-tight text-lg"
                    >
                        <slot name="header" />
                    </h2>
                </div>

                <div class="flex items-center gap-4">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                class="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition"
                            >
                                {{ $page.props.auth.user.name }}
                                <img
                                    :src="`https://ui-avatars.com/api/?name=${$page.props.auth.user.name}&background=f3f4f6&color=333`"
                                    class="h-8 w-8 rounded-full border border-gray-200"
                                />
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">
                                Profile
                            </DropdownLink>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="p-6 overflow-y-auto">
                <slot />
            </main>

            <footer
                class="mt-auto bg-white border-t p-4 text-xs text-gray-500 flex justify-between"
            >
                <div><b>Copyright &copy; 2026</b> Yuri Agent Management.</div>
                <div class="hidden sm:block"><b>Version</b> 1.0.0-dev</div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* Transisi halus untuk sidebar */
aside {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
