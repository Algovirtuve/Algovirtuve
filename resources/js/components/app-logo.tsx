import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex items-center">
                <div className="flex aspect-square size-8 items-center justify-center rounded-md">
                    <AppLogoIcon className="size-15 text-white" />
                </div>
                <div className="ml-1 grid flex-1 text-left text-sm">
                    <span className="truncate text-xl font-semibold">
                        AlgoVirtuvė
                    </span>
                </div>
            </div>
        </>
    );
}
