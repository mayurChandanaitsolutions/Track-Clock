*** Settings ***
Library    SeleniumLibrary

*** Variables ***
${URL}     https://devtesting.chandanaitsolutions.com/
${BROWSER}    chrome

*** Test Cases ***
Admin Login Trackclock HR Test
    Open Browser   ${URL}    ${BROWSER}
    Maximize Browser Window
    Input Text    name=username    superadmin
    Input Text    name=password    Admin@123
    Click Button    xpath=//button[@type='submit']
    Wait Until Page Contains    Dashboard  10s
    Close Browser
    