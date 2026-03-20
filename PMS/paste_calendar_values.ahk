#NoEnv
#SingleInstance Force
SendMode Input
SetWorkingDir %A_ScriptDir%
SetTitleMatchMode, 2

global scriptDeadline := 0
global scriptTimeoutMs := 900000
global calendarClipboardProperties := []
global calendarRunCounts := {}
global calendarMenuVisible := false
global calendarStandbyReady := false
global calendarLoopRunning := false
global calendarStopRequested := false
global calendarCurrentPropertyIndex := 1
global calendarCurrentCategoryIndex := 1
global calendarCurrentModeIndex := 1
global CalendarPasteProperty := 1
global CalendarPasteCategory := 1
global CalendarPasteMode := 1
global CalendarPasteRooms := ""
global CalendarPasteHint := ""

scriptDeadline := A_TickCount + scriptTimeoutMs
SetTimer, ScriptTimeoutCheck, 1000

MsgBox, 64, Pegar valores del calendario, Copia al portapapeles el bloque generado desde el calendario.`n`nF4 abre o actualiza el menu.`nMarca la seleccion con el boton Listo y luego presiona F4 para correr.`nDurante el loop, F4 o Esc lo detienen.`n`nSi no se usa durante 15 minutos, el script se cerrara.

F4::
HandleF4()
return

Esc::
if (calendarLoopRunning)
{
    calendarStopRequested := true
    scriptDeadline := A_TickCount + scriptTimeoutMs
    ToolTip, Deteniendo loop..., 20, 20
    Sleep, 500
    ToolTip
}
return

CalendarPastePropertyChange:
Gui, CalendarPaste:Submit, NoHide
calendarCurrentPropertyIndex := CalendarPasteProperty + 0
calendarCurrentCategoryIndex := 1
RefreshCalendarCategoryDropdown()
UpdateCalendarPasteMenu(calendarCurrentPropertyIndex, calendarCurrentCategoryIndex, CalendarPasteMode + 0)
return

CalendarPasteCategoryChange:
Gui, CalendarPaste:Submit, NoHide
calendarCurrentCategoryIndex := CalendarPasteCategory + 0
UpdateCalendarPasteMenu(CalendarPasteProperty + 0, calendarCurrentCategoryIndex, CalendarPasteMode + 0)
return

CalendarPasteModeChange:
Gui, CalendarPaste:Submit, NoHide
calendarCurrentModeIndex := CalendarPasteMode + 0
UpdateCalendarPasteMenu(CalendarPasteProperty + 0, CalendarPasteCategory + 0, calendarCurrentModeIndex)
return

CalendarPasteReady:
Gui, CalendarPaste:Submit, NoHide
calendarCurrentPropertyIndex := CalendarPasteProperty + 0
calendarCurrentCategoryIndex := CalendarPasteCategory + 0
calendarCurrentModeIndex := CalendarPasteMode + 0
calendarStandbyReady := true
scriptDeadline := A_TickCount + scriptTimeoutMs
UpdateCalendarPasteMenu(calendarCurrentPropertyIndex, calendarCurrentCategoryIndex, calendarCurrentModeIndex)
return

CalendarPasteCancel:
CalendarPasteGuiClose:
CalendarPasteGuiEscape:
calendarMenuVisible := false
calendarStandbyReady := false
Gui, CalendarPaste:Destroy
return

ScriptTimeoutCheck:
if (A_TickCount < scriptDeadline)
    return
SetTimer, ScriptTimeoutCheck, Off
MsgBox, 48, Pegar valores del calendario, El tiempo de inactividad de 15 minutos expiro.`nEl script se cerrara.
ExitApp

HandleF4()
{
    global scriptDeadline
    global scriptTimeoutMs
    global calendarMenuVisible
    global calendarStandbyReady
    global calendarLoopRunning
    global calendarStopRequested

    if (A_TickCount >= scriptDeadline)
    {
        Gosub, ScriptTimeoutCheck
        return
    }

    scriptDeadline := A_TickCount + scriptTimeoutMs

    if (calendarLoopRunning)
    {
        calendarStopRequested := true
        ToolTip, Deteniendo loop..., 20, 20
        Sleep, 500
        ToolTip
        return
    }

    rawClipboard := Trim(Clipboard)
    if (rawClipboard = "")
    {
        MsgBox, 48, Pegar valores del calendario, El portapapeles esta vacio.`nCopia primero los datos desde el calendario y vuelve a presionar F4.
        return
    }

    if (!LoadCalendarClipboardData(rawClipboard))
    {
        MsgBox, 48, Pegar valores del calendario, El portapapeles no tiene el formato esperado del calendario.
        return
    }

    if (!calendarMenuVisible)
    {
        ShowCalendarPasteMenu()
        return
    }

    Gui, CalendarPaste:Submit, NoHide
    if (!calendarStandbyReady)
    {
        UpdateCalendarPasteMenu(CalendarPasteProperty + 0, CalendarPasteCategory + 0, CalendarPasteMode + 0)
        return
    }

    StartCalendarPasteLoop(CalendarPasteProperty + 0, CalendarPasteCategory + 0, CalendarPasteMode + 0)
}

LoadCalendarClipboardData(rawText)
{
    global calendarClipboardProperties
    global calendarCurrentPropertyIndex
    global calendarCurrentCategoryIndex
    global calendarCurrentModeIndex

    normalized := StrReplace(rawText, "`r", "")
    lines := StrSplit(normalized, "`n")
    properties := []
    currentProperty := ""
    currentCategory := ""

    for index, rawLine in lines
    {
        line := Trim(rawLine)
        if (line = "")
            continue

        if (RegExMatch(line, "^Propiedad:\s*(.+)$", match))
        {
            currentProperty := {name: Trim(match1), categories: []}
            properties.Push(currentProperty)
            currentCategory := ""
            continue
        }

        if (!IsObject(currentProperty))
            continue

        if (RegExMatch(line, "^(.*)\((.*)\):$", match))
        {
            currentCategory := {name: Trim(match1), roomCodes: Trim(match2), availability: "", price: "", priceUsd: ""}
            currentProperty.categories.Push(currentCategory)
            continue
        }

        if (!IsObject(currentCategory))
            continue

        if (RegExMatch(line, "^- Disponibilidad:\s*(.*)$", match))
        {
            currentCategory.availability := Trim(match1)
            continue
        }

        if (RegExMatch(line, "^- Precio en pesos:\s*(.*)$", match))
        {
            currentCategory.price := Trim(match1)
            continue
        }

        if (RegExMatch(line, "^- Precio USD:\s*(.*)$", match))
        {
            currentCategory.priceUsd := Trim(match1)
            continue
        }
    }

    validProperties := []
    for index, property in properties
    {
        if (IsObject(property) && IsObject(property.categories) && property.categories.MaxIndex() >= 1)
            validProperties.Push(property)
    }

    if (!IsObject(validProperties) || validProperties.MaxIndex() < 1)
        return false

    calendarClipboardProperties := validProperties
    if (calendarCurrentPropertyIndex < 1 || calendarCurrentPropertyIndex > validProperties.MaxIndex())
        calendarCurrentPropertyIndex := 1
    if (calendarCurrentModeIndex < 1 || calendarCurrentModeIndex > 3)
        calendarCurrentModeIndex := 1
    categories := GetCalendarCategoriesForProperty(calendarCurrentPropertyIndex)
    if (!IsObject(categories) || calendarCurrentCategoryIndex < 1 || calendarCurrentCategoryIndex > categories.MaxIndex())
        calendarCurrentCategoryIndex := 1
    return true
}

ShowCalendarPasteMenu()
{
    global calendarCurrentPropertyIndex
    global calendarCurrentCategoryIndex
    global calendarCurrentModeIndex
    global calendarMenuVisible
    global calendarStandbyReady

    propertyOptions := BuildCalendarPropertyOptions()

    Gui, CalendarPaste:Destroy
    Gui, CalendarPaste:New, +AlwaysOnTop +OwnDialogs, Pegar valores del calendario
    Gui, CalendarPaste:Margin, 12, 12
    Gui, CalendarPaste:Add, Text,, Propiedad
    Gui, CalendarPaste:Add, DropDownList, vCalendarPasteProperty gCalendarPastePropertyChange AltSubmit xm y+4 w460 Choose%calendarCurrentPropertyIndex%, %propertyOptions%
    Gui, CalendarPaste:Add, Text, xm y+12, Categoria
    Gui, CalendarPaste:Add, DropDownList, vCalendarPasteCategory gCalendarPasteCategoryChange AltSubmit xm y+4 w460,
    Gui, CalendarPaste:Add, Text, xm y+12, Tipo de dato
    Gui, CalendarPaste:Add, DropDownList, vCalendarPasteMode gCalendarPasteModeChange AltSubmit xm y+4 w220 Choose%calendarCurrentModeIndex%, Ocupacion|Precio|Precio USD
    Gui, CalendarPaste:Add, Text, xm y+12, Habitaciones
    Gui, CalendarPaste:Add, Text, vCalendarPasteRooms xm y+4 w460,
    Gui, CalendarPaste:Add, Text, xm y+12 vCalendarPasteHint w460,
    Gui, CalendarPaste:Add, Button, xm y+16 w120 Default gCalendarPasteReady, Listo
    Gui, CalendarPaste:Add, Button, x+8 w110 gCalendarPasteCancel, Cerrar
    calendarMenuVisible := true
    calendarStandbyReady := false
    RefreshCalendarCategoryDropdown()
    UpdateCalendarPasteMenu(calendarCurrentPropertyIndex, calendarCurrentCategoryIndex, calendarCurrentModeIndex)
    Gui, CalendarPaste:Show, AutoSize Center
}

BuildCalendarPropertyOptions()
{
    global calendarClipboardProperties

    propertyOptions := ""
    for index, property in calendarClipboardProperties
    {
        label := property.name
        if (propertyOptions != "")
            propertyOptions .= "|"
        propertyOptions .= label
    }
    return propertyOptions
}

RefreshCalendarCategoryDropdown()
{
    global calendarCurrentPropertyIndex
    global calendarCurrentCategoryIndex

    categories := GetCalendarCategoriesForProperty(calendarCurrentPropertyIndex)
    categoryOptions := ""
    for index, category in categories
    {
        label := category.name
        if (category.roomCodes != "")
            label .= " (" . category.roomCodes . ")"
        if (categoryOptions != "")
            categoryOptions .= "|"
        categoryOptions .= label
    }
    if (calendarCurrentCategoryIndex < 1 || calendarCurrentCategoryIndex > categories.MaxIndex())
        calendarCurrentCategoryIndex := 1
    GuiControl, CalendarPaste:, CalendarPasteCategory, |%categoryOptions%
    GuiControl, CalendarPaste:Choose, CalendarPasteCategory, %calendarCurrentCategoryIndex%
}

GetCalendarCategoriesForProperty(propertyIndex)
{
    global calendarClipboardProperties

    if (!IsObject(calendarClipboardProperties) || propertyIndex < 1 || propertyIndex > calendarClipboardProperties.MaxIndex())
        return []
    property := calendarClipboardProperties[propertyIndex]
    if (!IsObject(property) || !IsObject(property.categories))
        return []
    return property.categories
}

UpdateCalendarPasteMenu(propertyIndex, categoryIndex, modeIndex)
{
    global calendarStandbyReady

    categories := GetCalendarCategoriesForProperty(propertyIndex)
    category := ""
    if (IsObject(categories) && categoryIndex >= 1 && categoryIndex <= categories.MaxIndex())
        category := categories[categoryIndex]

    roomCodes := "Sin habitaciones"
    if (IsObject(category) && category.roomCodes != "")
        roomCodes := category.roomCodes

    modeName := GetCalendarModeName(modeIndex)
    runCount := GetCalendarRunCount(propertyIndex, categoryIndex, modeIndex)
    if (calendarStandbyReady)
        hint := "Standby para " . modeName . " (" . runCount . "). Presiona F4 para correr."
    else
        hint := "Selecciona propiedad, categoria y tipo. Corridas: (" . runCount . ")"

    GuiControl, CalendarPaste:, CalendarPasteRooms, %roomCodes%
    GuiControl, CalendarPaste:, CalendarPasteHint, %hint%
}

GetCalendarModeName(modeIndex)
{
    if (modeIndex = 1)
        return "Ocupacion"
    if (modeIndex = 2)
        return "Precio"
    if (modeIndex = 3)
        return "Precio USD"
    return "Dato"
}

GetCalendarSelectionKey(propertyIndex, categoryIndex, modeIndex)
{
    global calendarClipboardProperties
    propertyName := ""
    if (IsObject(calendarClipboardProperties) && propertyIndex >= 1 && propertyIndex <= calendarClipboardProperties.MaxIndex())
        propertyName := calendarClipboardProperties[propertyIndex].name
    return propertyName . "|" . categoryIndex . "|" . modeIndex
}

GetCalendarRunCount(propertyIndex, categoryIndex, modeIndex)
{
    global calendarRunCounts
    key := GetCalendarSelectionKey(propertyIndex, categoryIndex, modeIndex)
    if (!calendarRunCounts.HasKey(key))
        return 0
    return calendarRunCounts[key]
}

IncrementCalendarRunCount(propertyIndex, categoryIndex, modeIndex)
{
    global calendarRunCounts
    key := GetCalendarSelectionKey(propertyIndex, categoryIndex, modeIndex)
    if (!calendarRunCounts.HasKey(key))
        calendarRunCounts[key] := 0
    calendarRunCounts[key] := calendarRunCounts[key] + 1
    return calendarRunCounts[key]
}

GetCalendarCategorySeries(propertyIndex, categoryIndex, modeIndex)
{
    categories := GetCalendarCategoriesForProperty(propertyIndex)
    if (!IsObject(categories) || categoryIndex < 1 || categoryIndex > categories.MaxIndex())
        return ""
    category := categories[categoryIndex]
    if (!IsObject(category))
        return ""
    if (modeIndex = 1)
        return category.availability
    if (modeIndex = 2)
        return category.price
    if (modeIndex = 3)
        return category.priceUsd
    return ""
}

ParseCalendarSeriesValues(rawSeries)
{
    values := []
    normalized := StrReplace(rawSeries, "`r", "")
    normalized := StrReplace(normalized, "`n", ",")
    normalized := StrReplace(normalized, "`t", ",")
    items := StrSplit(normalized, ",")
    for index, rawValue in items
        values.Push(Trim(rawValue))
    return values
}

StartCalendarPasteLoop(propertyIndex, categoryIndex, modeIndex)
{
    global scriptDeadline
    global scriptTimeoutMs
    global calendarStandbyReady
    global calendarLoopRunning
    global calendarStopRequested
    global calendarCurrentPropertyIndex
    global calendarCurrentCategoryIndex
    global calendarCurrentModeIndex

    seriesText := GetCalendarCategorySeries(propertyIndex, categoryIndex, modeIndex)
    if (seriesText = "")
    {
        MsgBox, 48, Pegar valores del calendario, No hay valores disponibles para la seleccion actual.
        return
    }

    values := ParseCalendarSeriesValues(seriesText)
    if (!IsObject(values) || values.MaxIndex() < 1)
    {
        MsgBox, 48, Pegar valores del calendario, No se pudieron leer valores validos para la seleccion actual.
        return
    }

    calendarCurrentPropertyIndex := propertyIndex
    calendarCurrentCategoryIndex := categoryIndex
    calendarCurrentModeIndex := modeIndex
    calendarStandbyReady := false
    calendarLoopRunning := true
    calendarStopRequested := false
    scriptDeadline := A_TickCount + scriptTimeoutMs

    RunCalendarPasteCycle(values)
}

RunCalendarPasteCycle(values)
{
    global calendarLoopRunning
    global calendarStopRequested
    global calendarCurrentPropertyIndex
    global calendarCurrentCategoryIndex
    global calendarCurrentModeIndex

    ToolTip, Ejecutando loop... F4 o Esc para detener., 20, 20
    Sleep, 350
    ToolTip

    SetKeyDelay, 40, 40

    interrupted := false
    for index, rawValue in values
    {
        if (calendarStopRequested)
        {
            interrupted := true
            break
        }
        value := Trim(rawValue)
        if (value != "")
            SendInput %value%
        SendInput {Tab}
        Sleep, 200
    }

    calendarLoopRunning := false
    calendarStopRequested := false
    runCount := IncrementCalendarRunCount(calendarCurrentPropertyIndex, calendarCurrentCategoryIndex, calendarCurrentModeIndex)

    if (interrupted)
        ToolTip, Loop detenido. Corridas: (%runCount%)., 20, 20
    else
        ToolTip, Loop terminado. Corridas: (%runCount%)., 20, 20
    Sleep, 1200
    ToolTip

    ShowCalendarPasteMenu()
}
