*** Settings ***
Library    SeleniumLibrary

*** Variables ***
${URL}     https://devtesting.chandanaitsolutions.com/
${BROWSER}    chrome

*** Test Cases ***
Login Trackclock HR Test
    Open Browser   ${URL}    ${BROWSER}
    Maximize Browser Window
    Input Text    name=username    MG001
    Input Text    name=password    MG001
    Click Button    xpath=//button[@type='submit']
    Wait Until Page Contains    Dashboard  10s
    Close Browser
    