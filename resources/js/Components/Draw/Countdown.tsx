import { AnimatePresence, motion } from 'motion/react';

export default function Countdown({ value }: { value: number }) {
    return (
        <div className="flex h-full w-full items-center justify-center">
            <AnimatePresence mode="wait">
                <motion.span
                    key={value}
                    initial={{ opacity: 0, scale: 0.6 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 1.4 }}
                    transition={{ duration: 0.4, ease: 'easeOut' }}
                    className="gold-gradient-text text-[14rem] font-extrabold leading-none"
                >
                    {value > 0 ? value : 'GO'}
                </motion.span>
            </AnimatePresence>
        </div>
    );
}
