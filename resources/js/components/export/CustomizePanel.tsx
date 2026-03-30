import { useTranslation } from 'react-i18next';
import type {
    FontPairingDef,
    SceneBreakStyleDef,
} from '@/components/export/types';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/Accordion';
import Badge from '@/components/ui/Badge';
import Select from '@/components/ui/Select';
import ToggleRow from '@/components/ui/ToggleRow';

interface CustomizePanelProps {
    fontPairings: FontPairingDef[];
    sceneBreakStyles: SceneBreakStyleDef[];
    selectedFontPairing: string;
    selectedSceneBreakStyle: string;
    dropCaps: boolean;
    onFontPairingChange: (value: string) => void;
    onSceneBreakStyleChange: (value: string) => void;
    onDropCapsChange: (value: boolean) => void;
    isCustomized: boolean;
}

export default function CustomizePanel({
    fontPairings,
    sceneBreakStyles,
    selectedFontPairing,
    selectedSceneBreakStyle,
    dropCaps,
    onFontPairingChange,
    onSceneBreakStyleChange,
    onDropCapsChange,
    isCustomized,
}: CustomizePanelProps) {
    const { t } = useTranslation('export');

    return (
        <Accordion type="single" collapsible>
            <AccordionItem value="customize" className="border-b-0">
                <AccordionTrigger className="py-2.5">
                    <span className="flex items-center gap-2">
                        <span className="text-[13px] text-ink-soft">
                            {t('customize')}
                        </span>
                        {isCustomized && (
                            <Badge variant="warning" className="text-[10px]">
                                {t('customLabel')}
                            </Badge>
                        )}
                    </span>
                </AccordionTrigger>
                <AccordionContent className="pb-2">
                    <div className="flex flex-col gap-3">
                        <p className="text-[11px] text-ink-faint">
                            {t('customizeDescription')}
                        </p>

                        {/* Font Pairing */}
                        <div className="flex items-center justify-between">
                            <span className="text-[13px] text-ink-soft">
                                {t('fontPairing')}
                            </span>
                            <Select
                                variant="compact"
                                value={selectedFontPairing}
                                onChange={(e) =>
                                    onFontPairingChange(e.target.value)
                                }
                                className="w-auto"
                            >
                                {fontPairings.map((fp) => (
                                    <option key={fp.value} value={fp.value}>
                                        {fp.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {/* Scene Break Style */}
                        <div className="flex items-center justify-between">
                            <span className="text-[13px] text-ink-soft">
                                {t('sceneBreakStyle')}
                            </span>
                            <Select
                                variant="compact"
                                value={selectedSceneBreakStyle}
                                onChange={(e) =>
                                    onSceneBreakStyleChange(e.target.value)
                                }
                                className="w-auto"
                            >
                                {sceneBreakStyles.map((s) => (
                                    <option key={s.value} value={s.value}>
                                        {s.label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {/* Drop Caps */}
                        <ToggleRow
                            label={t('dropCaps')}
                            checked={dropCaps}
                            onChange={() => onDropCapsChange(!dropCaps)}
                            border={false}
                        />
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    );
}
