import { alpha, createTheme } from '@mui/material/styles';

const brandRed = '#b42325';
const brandDark = '#171326';

export const dvxTheme = createTheme({
    palette: {
        mode: 'light',
        primary: {
            main: brandRed,
            dark: '#821519',
            light: '#de5040',
            contrastText: '#ffffff',
        },
        secondary: {
            main: '#d88a2c',
            dark: '#aa671b',
            light: '#f1b55e',
        },
        background: {
            default: '#f7f7fa',
            paper: '#ffffff',
        },
        text: {
            primary: '#202230',
            secondary: '#747889',
        },
        divider: '#e7e8ed',
        success: {
            main: '#159669',
        },
        warning: {
            main: '#d58a1f',
        },
        error: {
            main: '#d02d32',
        },
    },
    shape: {
        borderRadius: 12,
    },
    typography: {
        fontFamily:
            "'Manrope Variable', 'Manrope', ui-sans-serif, system-ui, -apple-system, sans-serif",
        h1: {
            fontWeight: 750,
            letterSpacing: '-0.04em',
        },
        h2: {
            fontWeight: 750,
            letterSpacing: '-0.035em',
        },
        h3: {
            fontWeight: 700,
            letterSpacing: '-0.025em',
        },
        h4: {
            fontWeight: 700,
            letterSpacing: '-0.02em',
        },
        button: {
            fontWeight: 700,
            textTransform: 'none',
        },
    },
    components: {
        MuiCssBaseline: {
            styleOverrides: {
                body: {
                    backgroundColor: '#f7f7fa',
                },
                '*': {
                    scrollbarWidth: 'thin',
                    scrollbarColor: `${alpha(brandRed, 0.55)} transparent`,
                },
            },
        },
        MuiButton: {
            defaultProps: {
                disableElevation: true,
            },
            styleOverrides: {
                root: {
                    minHeight: 42,
                    borderRadius: 12,
                    paddingInline: 18,
                    '&.MuiButton-containedPrimary': {
                        background: `linear-gradient(120deg, ${brandRed}, #d43d25)`,
                        boxShadow: `0 10px 24px ${alpha(brandRed, 0.2)}`,
                    },
                },
            },
        },
        MuiPaper: {
            styleOverrides: {
                root: {
                    backgroundImage: 'none',
                },
                rounded: {
                    borderRadius: 18,
                },
            },
        },
        MuiCard: {
            styleOverrides: {
                root: {
                    border: '1px solid #e8e9ee',
                    boxShadow: '0 12px 35px rgba(25, 27, 38, 0.055)',
                },
            },
        },
        MuiOutlinedInput: {
            styleOverrides: {
                root: {
                    borderRadius: 12,
                    backgroundColor: '#ffffff',
                    '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
                        borderColor: brandRed,
                        borderWidth: 1,
                    },
                },
            },
        },
        MuiChip: {
            styleOverrides: {
                root: {
                    borderRadius: 999,
                    fontWeight: 650,
                },
            },
        },
        MuiDialog: {
            styleOverrides: {
                paper: {
                    borderRadius: 22,
                },
            },
        },
        MuiTooltip: {
            styleOverrides: {
                tooltip: {
                    borderRadius: 8,
                    backgroundColor: brandDark,
                    fontSize: 12,
                },
            },
        },
    },
});
